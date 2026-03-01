<?php

namespace App\Services;

use App\Models\CodeChunk;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Retriever
{
    private const DEFAULT_TOP_K = 10;
    private const MAX_TOP_K = 50;
    private const DEFAULT_THRESHOLD = 0.4;
    private const CACHE_TTL_SECONDS = 300; // 5 minutes

    /** Score multiplier when the chunk name contains a query keyword. */
    private const KEYWORD_NAME_BOOST = 1.3;

    /** Score multiplier when the chunk file_path contains a query keyword. */
    private const KEYWORD_PATH_BOOST = 1.2;

    /** Rough characters-per-token estimate used for context window budgeting. */
    private const CHARS_PER_TOKEN = 4;

    private const STOPWORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'do', 'for',
        'from', 'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'or',
        'that', 'the', 'this', 'to', 'was', 'were', 'will', 'with',
        'how', 'what', 'where', 'when', 'which', 'who', 'why', 'can',
        'get', 'set', 'use', 'used', 'using', 'also', 'not',
    ];

    public function __construct(
        private EmbeddingService $embeddingService,
        private VectorDBService $vectorDb,
    ) {}

    /**
     * Basic semantic vector search against the repository's Qdrant collection.
     *
     * Embeds the query, searches Qdrant, and returns ranked results with
     * chunk metadata hydrated. Results are cached for 5 minutes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function retrieveRelevantChunks(
        int $repositoryId,
        string $query,
        int $topK = self::DEFAULT_TOP_K,
        float $threshold = self::DEFAULT_THRESHOLD,
    ): array {
        $topK = min($topK, self::MAX_TOP_K);

        $cacheKey = $this->cacheKey($repositoryId, "vector:{$query}:{$topK}:{$threshold}");

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($repositoryId, $query, $topK, $threshold) {
            $vector = $this->embeddingService->generateEmbedding($query);

            if (empty($vector)) {
                Log::warning('Retriever: empty embedding returned for query', ['repository_id' => $repositoryId]);

                return [];
            }

            $collectionName = "repo_{$repositoryId}";
            $results        = $this->vectorDb->search($collectionName, $vector, $topK, $threshold);

            return $this->formatResults($results, $repositoryId);
        });
    }

    /**
     * Hybrid search combining vector similarity with keyword boosting.
     *
     * Runs semantic search, then re-scores results by boosting chunks whose
     * name or file path contains significant keywords from the query.
     *
     * @return array<int, array<string, mixed>>
     */
    public function hybridSearch(int $repositoryId, string $query, int $topK = self::DEFAULT_TOP_K): array
    {
        $topK = min($topK, self::MAX_TOP_K);

        $cacheKey = $this->cacheKey($repositoryId, "hybrid:{$query}:{$topK}");

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($repositoryId, $query, $topK) {
            // Fetch more candidates than needed so boosting can re-rank effectively.
            $candidates = $this->retrieveRelevantChunks($repositoryId, $query, min($topK * 3, self::MAX_TOP_K));

            if (empty($candidates)) {
                return [];
            }

            $keywords = $this->extractKeywords($query);

            if (empty($keywords)) {
                return array_slice($candidates, 0, $topK);
            }

            foreach ($candidates as &$chunk) {
                $score = $chunk['score'];
                $name  = strtolower((string) ($chunk['name'] ?? ''));
                $path  = strtolower((string) ($chunk['file_path'] ?? ''));

                foreach ($keywords as $keyword) {
                    if ($name !== '' && str_contains($name, $keyword)) {
                        $score *= self::KEYWORD_NAME_BOOST;
                        break;
                    }
                }

                foreach ($keywords as $keyword) {
                    if ($path !== '' && str_contains($path, $keyword)) {
                        $score *= self::KEYWORD_PATH_BOOST;
                        break;
                    }
                }

                $chunk['score'] = $score;
            }
            unset($chunk);

            usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

            return array_slice($candidates, 0, $topK);
        });
    }

    /**
     * Hybrid search enriched with chunks from files imported by the top results.
     *
     * After retrieving the primary results, parses Python and JS/TS import
     * statements in each chunk's content and fetches all chunks from those
     * referenced files as additional context.
     *
     * @return array<int, array<string, mixed>>
     */
    public function retrieveWithContext(int $repositoryId, string $query, int $topK = self::DEFAULT_TOP_K): array
    {
        $primary = $this->hybridSearch($repositoryId, $query, $topK);

        if (empty($primary)) {
            return [];
        }

        $relatedFilePaths = [];

        foreach ($primary as $chunk) {
            $content  = (string) ($chunk['content'] ?? '');
            $filePath = (string) ($chunk['file_path'] ?? '');
            $language = (string) ($chunk['language'] ?? '');

            $imports = $this->detectImports($content, $filePath, $language);

            foreach ($imports as $importedPath) {
                $relatedFilePaths[$importedPath] = true;
            }
        }

        $additional = [];

        foreach (array_keys($relatedFilePaths) as $importedPath) {
            $fileChunks = $this->retrieveByFilePath($repositoryId, $importedPath);
            $additional = array_merge($additional, $fileChunks);
        }

        $merged = array_merge($primary, $additional);

        return $this->deduplicateChunks($merged);
    }

    /**
     * Retrieve all indexed chunks belonging to a specific file path.
     *
     * Uses Qdrant's scroll-by-filter API to fetch all points with a matching
     * file_path payload, since vector_id is not stored back in PostgreSQL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function retrieveByFilePath(int $repositoryId, string $filePath): array
    {
        $collectionName = "repo_{$repositoryId}";
        $points         = $this->vectorDb->scrollByFilter($collectionName, 'file_path', $filePath);

        return array_map(fn(array $point) => $this->normalisePoint($point, 1.0, $repositoryId), $points);
    }

    /**
     * Parse an error log, extract file/line references, and retrieve relevant chunks.
     *
     * Combines two signals:
     *   1. Semantic search on the error message itself.
     *   2. Direct retrieval of chunks that cover the file/line pairs found in the
     *      stack trace.
     *
     * @return array<int, array<string, mixed>>
     */
    public function retrieveByErrorTrace(int $repositoryId, string $errorLog): array
    {
        $lines     = explode("\n", $errorLog);
        $errorMessage = trim($lines[0] ?? $errorLog);

        // Semantic search on the error message gives broad context.
        $semantic = $this->retrieveRelevantChunks($repositoryId, $errorMessage);

        // Parse stack trace for specific file + line references.
        $traceRefs = $this->parseErrorTrace($errorLog);

        $traceChunks = [];

        foreach ($traceRefs as $ref) {
            $chunks = CodeChunk::where('repository_id', $repositoryId)
                ->where('file_path', 'like', '%' . basename($ref['file']) . '%')
                ->where('start_line', '<=', $ref['line'])
                ->where('end_line', '>=', $ref['line'])
                ->get()
                ->toArray();

            foreach ($chunks as $chunk) {
                // Fetch content from Qdrant for this specific chunk via file path scroll.
                $fileChunks = $this->retrieveByFilePath($repositoryId, $chunk['file_path']);

                foreach ($fileChunks as $fc) {
                    if (
                        ($fc['start_line'] ?? 0) <= $ref['line']
                        && ($fc['end_line'] ?? 0) >= $ref['line']
                    ) {
                        $fc['score']   = 1.0; // Treat exact line matches as high relevance.
                        $fc['file_path'] = str_replace('/tmp/repos/' . $repositoryId . '/', '/', $fc['file_path']);
                        $traceChunks[] = $fc;
                    }
                }
            }
        }

        $merged = array_merge($semantic, $traceChunks);

        return $this->deduplicateChunks($merged);
    }

    /**
     * Format retrieved chunks into a prompt-ready string for the LLM.
     *
     * Each chunk is rendered as a labelled fenced code block. Chunks are
     * included in relevance order until the estimated token budget is reached.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     */
    public function formatContextForLLM(array $chunks, int $maxTokens = 150_000): string
    {
        $budget  = $maxTokens * self::CHARS_PER_TOKEN; // convert tokens → chars
        $used    = 0;
        $sections = [];

        foreach ($chunks as $chunk) {
            $filePath  = (string) ($chunk['file_path'] ?? 'unknown');
            $startLine = (int) ($chunk['start_line'] ?? 0);
            $endLine   = (int) ($chunk['end_line'] ?? 0);
            $language  = (string) ($chunk['language'] ?? '');
            $content   = (string) ($chunk['content'] ?? '');

            $lineRef = $startLine > 0 ? ":{$startLine}-{$endLine}" : '';
            $block   = "📄 {$filePath}{$lineRef}\n```{$language}\n{$content}\n```";

            $blockLen = strlen($block);

            if ($used + $blockLen > $budget) {
                break;
            }

            $sections[] = $block;
            $used       += $blockLen;
        }

        return implode("\n\n", $sections);
    }

    /**
     * Parse Python, JavaScript, Java, and PHP stack trace formats.
     *
     * Supported formats:
     *   Python:     File "app.py", line 42, in function_name
     *   JavaScript: at functionName (file.js:42:15)
     *   Java:       at package.Class.method(File.java:42)
     *   PHP:        #0 /path/to/file.php(42): ClassName->method()
     *   PHP (fatal): in /path/to/file.php on line 42
     *
     * @return array<int, array{file: string, line: int}>
     */
    private function parseErrorTrace(string $errorLog): array
    {
        $refs = [];

        // Python: File "app.py", line 42, in some_function
        preg_match_all('/File "([^"]+)",\s+line\s+(\d+)/i', $errorLog, $pythonMatches, PREG_SET_ORDER);

        foreach ($pythonMatches as $m) {
            $refs[] = ['file' => $m[1], 'line' => (int) $m[2]];
        }

        // JavaScript: at functionName (path/file.js:42:15) or at path/file.js:42:15
        preg_match_all('/at\s+(?:\S+\s+)?\(([^)]+\.(?:js|ts|jsx|tsx)):(\d+)(?::\d+)?\)/i', $errorLog, $jsMatches, PREG_SET_ORDER);

        foreach ($jsMatches as $m) {
            $refs[] = ['file' => $m[1], 'line' => (int) $m[2]];
        }

        // Java: at com.example.Class.method(File.java:42)
        preg_match_all('/at\s+[\w.$]+\((\w+\.java):(\d+)\)/i', $errorLog, $javaMatches, PREG_SET_ORDER);

        foreach ($javaMatches as $m) {
            $refs[] = ['file' => $m[1], 'line' => (int) $m[2]];
        }

        // PHP stack frame: #0 /path/to/file.php(42): ...
        preg_match_all('/#\d+\s+([^\s(]+\.php)\((\d+)\)/i', $errorLog, $phpFrameMatches, PREG_SET_ORDER);

        foreach ($phpFrameMatches as $m) {
            $refs[] = ['file' => $m[1], 'line' => (int) $m[2]];
        }

        // PHP fatal/exception: in /path/to/file.php on line 42
        preg_match_all('/in\s+([^\s]+\.php)\s+on\s+line\s+(\d+)/i', $errorLog, $phpFatalMatches, PREG_SET_ORDER);

        foreach ($phpFatalMatches as $m) {
            $refs[] = ['file' => $m[1], 'line' => (int) $m[2]];
        }

        // Deduplicate by file + line.
        $seen   = [];
        $unique = [];

        foreach ($refs as $ref) {
            $key = $ref['file'] . ':' . $ref['line'];

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[]   = $ref;
            }
        }

        return $unique;
    }

    /**
     * Extract significant keywords from a query string.
     *
     * Lowercases the input, splits on non-alphanumeric characters, filters
     * stopwords and short tokens, and returns unique terms.
     *
     * @return string[]
     */
    private function extractKeywords(string $query): array
    {
        $tokens = preg_split('/[^a-z0-9_]+/i', strtolower($query), -1, PREG_SPLIT_NO_EMPTY);

        $keywords = array_filter(
            $tokens ?? [],
            fn (string $token) => strlen($token) > 2 && !in_array($token, self::STOPWORDS, true)
        );

        return array_values(array_unique($keywords));
    }

    /**
     * Detect imported file paths from chunk content.
     *
     * For Python, JS/TS, and PHP files, attempts to resolve relative import paths
     * relative to the chunk's own file directory so the resulting path can
     * be matched against `file_path` entries stored in Qdrant.
     *
     * @return string[]
     */
    private function detectImports(string $content, string $currentFilePath, string $language): array
    {
        $imports = [];
        $dir     = dirname($currentFilePath);

        if (in_array($language, ['python'], true)) {
            // from .module import X  or  import module
            preg_match_all('/^(?:from\s+([\w.]+)|import\s+([\w.]+))/m', $content, $m, PREG_SET_ORDER);

            foreach ($m as $match) {
                $module = $match[1] ?: $match[2];

                // Only attempt resolution for relative imports (starting with dot)
                if (str_starts_with($module, '.')) {
                    $relativePath = str_replace('.', '/', ltrim($module, '.')) . '.py';
                    $imports[]    = $dir . '/' . $relativePath;
                }
            }
        }

        if (in_array($language, ['javascript', 'typescript'], true)) {
            // import ... from './relative/path'  or  require('./relative/path')
            preg_match_all("/(?:import\s+[^'\"]*from\s+|require\s*\(\s*)['\"](\.[^'\"]+)['\"]/", $content, $m, PREG_SET_ORDER);

            foreach ($m as $match) {
                $raw = $match[1];

                // Strip query strings / hash fragments.
                $raw = preg_replace('/[?#].*$/', '', $raw) ?? $raw;

                // Attempt to build an absolute-ish path.
                $resolved  = $dir . '/' . $raw;
                $imports[] = $resolved;

                // Try with common extensions if no extension present.
                if (!str_contains(basename($resolved), '.')) {
                    foreach (['.ts', '.tsx', '.js', '.jsx'] as $ext) {
                        $imports[] = $resolved . $ext;
                    }
                }
            }
        }

        if (in_array($language, ['php'], true)) {
            // require/include (once) with relative paths: require_once __DIR__ . '/path.php'
            // or: require './path.php', include '../other.php'
            preg_match_all(
                "/(?:require|include)(?:_once)?\s*(?:\(?\s*__DIR__\s*\.\s*)?['\"]([^'\"]+\.php)['\"]/i",
                $content,
                $m,
                PREG_SET_ORDER,
            );

            foreach ($m as $match) {
                $raw = $match[1];

                // Resolve relative paths against the current file's directory.
                $resolved  = str_starts_with($raw, '/') ? $raw : $dir . '/' . $raw;
                $imports[] = $resolved;
            }

            // PSR-4 use statements: use App\Services\SomeClass;
            // Convert namespace separator to directory separator and append .php.
            preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+\w+)?;/m', $content, $m, PREG_SET_ORDER);

            foreach ($m as $match) {
                $namespacePath = str_replace('\\', '/', $match[1]) . '.php';
                $imports[]     = $namespacePath;
            }
        }

        return array_values(array_unique($imports));
    }

    /**
     * Build a cache key for retrieval results.
     */
    private function cacheKey(int $repositoryId, string $discriminator): string
    {
        return "retriever:{$repositoryId}:" . md5($discriminator);
    }

    /**
     * Normalise a raw Qdrant search result into a flat chunk array.
     *
     * @param  array<string, mixed>  $result  Raw Qdrant result (with 'score' and 'payload').
     * @param  int  $repositoryId
     * @return array<string, mixed>
     */
    private function normalisePoint(array $result, float $defaultScore = 0.0, int $repositoryId): array
    {
        $payload = $result['payload'] ?? [];

        return [
            'vector_id'  => $result['id'] ?? null,
            'score'      => (float) ($result['score'] ?? $defaultScore),
            'file_path'  => str_replace('/tmp/repos/' . $repositoryId . '/', '/', $payload['file_path'] ?? null),
            'chunk_type' => $payload['chunk_type'] ?? null,
            'name'       => $payload['name'] ?? null,
            'content'    => $payload['content'] ?? null,
            'start_line' => isset($payload['start_line']) ? (int) $payload['start_line'] : null,
            'end_line'   => isset($payload['end_line']) ? (int) $payload['end_line'] : null,
            'language'   => $payload['language'] ?? null,
            'signature'  => $payload['signature'] ?? null,
            'docstring'  => $payload['docstring'] ?? null,
        ];
    }

    /**
     * Normalise and sort a list of raw Qdrant search results.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @param  int  $repositoryId
     * @return array<int, array<string, mixed>>
     */
    private function formatResults(array $results, int $repositoryId): array
    {
        $normalised = array_map(fn(array $r) => $this->normalisePoint(result: $r, repositoryId: $repositoryId), $results);

        usort($normalised, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $normalised;
    }

    /**
     * Remove duplicate chunks from a merged result set.
     *
     * A chunk is considered a duplicate when it shares the same file path and
     * overlapping line range as another chunk already in the list. When
     * duplicates exist, the one with the higher score is kept.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     * @return array<int, array<string, mixed>>
     */
    private function deduplicateChunks(array $chunks): array
    {
        usort($chunks, fn ($a, $b) => $b['score'] <=> $a['score']);

        $seen   = [];
        $unique = [];

        foreach ($chunks as $chunk) {
            $filePath  = (string) ($chunk['file_path'] ?? '');
            $startLine = (int) ($chunk['start_line'] ?? 0);
            $endLine   = (int) ($chunk['end_line'] ?? 0);

            $key = "{$filePath}:{$startLine}-{$endLine}";

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[]   = $chunk;
            }
        }

        return $unique;
    }
}
