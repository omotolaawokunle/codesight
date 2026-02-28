<?php

namespace App\Services;

use App\Models\CodeChunk;
use App\Models\Repository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Indexer
{
    /** Number of files sent to the AST service per batch. */
    private const FILE_BATCH_SIZE = 10;

    /** Maximum number of texts sent to the embedding API per call. */
    private const EMBEDDING_BATCH_SIZE = 100;

    public function __construct(
        private ASTServiceClient $astClient,
        private EmbeddingService $embeddingService,
        private VectorDBService $vectorDb,
    ) {}

    /**
     * Run the full indexing pipeline for a repository.
     *
     * Steps for each batch of files:
     *   1. Read file contents from disk
     *   2. Send to AST service for parsing
     *   3. Build embedding texts from chunk metadata + content
     *   4. Generate embeddings via Gemini (SDK caches for 1 hour)
     *   5. Upsert vectors into Qdrant collection
     *   6. Persist chunk metadata to PostgreSQL
     *   7. Update repository progress counters
     *
     * @param  string[]  $filePaths  Absolute paths to files to index.
     */
    public function run(Repository $repository, array $filePaths): void
    {
        $collectionName = "repo_{$repository->id}";
        $totalFiles     = count($filePaths);
        $indexedFiles   = 0;
        $totalChunks    = 0;

        Log::info('Indexer: starting pipeline', [
            'repository_id' => $repository->id,
            'total_files'   => $totalFiles,
            'collection'    => $collectionName,
        ]);

        $this->vectorDb->createCollection($collectionName);

        foreach (array_chunk($filePaths, self::FILE_BATCH_SIZE) as $batch) {
            try {
                $chunks = $this->parseFiles($batch);

                if (empty($chunks)) {
                    $indexedFiles += count($batch);
                    $this->updateProgress($repository, $indexedFiles, $totalChunks);
                    continue;
                }

                $vectors = $this->embedChunks($chunks);

                $this->storeVectors($collectionName, $chunks, $vectors);
                $this->storeChunkMetadata($repository->id, $chunks);

                $indexedFiles += count($batch);
                $totalChunks  += count($chunks);

                $this->updateProgress($repository, $indexedFiles, $totalChunks);

                Log::debug('Indexer: batch complete', [
                    'repository_id' => $repository->id,
                    'indexed_files' => $indexedFiles,
                    'total_files'   => $totalFiles,
                    'batch_chunks'  => count($chunks),
                ]);

            } catch (Throwable $e) {
                Log::error('Indexer: batch failed', [
                    'repository_id' => $repository->id,
                    'error'         => $e->getMessage(),
                    'files'         => $batch,
                ]);
                // Continue indexing remaining batches even if one fails.
                $indexedFiles += count($batch);
                $this->updateProgress($repository, $indexedFiles, $totalChunks);
            }
        }

        Log::info('Indexer: pipeline complete', [
            'repository_id' => $repository->id,
            'indexed_files' => $indexedFiles,
            'total_chunks'  => $totalChunks,
        ]);
    }

    /**
     * Read file contents and send them to the AST service for parsing.
     * Returns a flat list of code chunks across all files in the batch.
     *
     * @param  string[]  $filePaths
     * @return array<int, array<string, mixed>>
     */
    private function parseFiles(array $filePaths): array
    {
        $astRequests = [];

        foreach ($filePaths as $path) {
            $language = $this->astClient->detectLanguage($path);
            if ($language === null) {
                continue;
            }

            $content = @file_get_contents($path);
            if ($content === false || strlen($content) === 0) {
                continue;
            }

            $astRequests[] = [
                'filePath' => $path,
                'content'  => $content,
                'language' => $language,
            ];
        }

        if (empty($astRequests)) {
            return [];
        }

        $parseResults = $this->astClient->parseBatch($astRequests);

        $chunks = [];
        foreach ($parseResults as $result) {
            if (empty($result['chunks'])) {
                continue;
            }

            foreach ($result['chunks'] as $chunk) {
                $chunk['filePath'] = $result['filePath'];
                $chunk['language'] = $result['language'];
                $chunks[]          = $chunk;
            }
        }

        return $chunks;
    }

    /**
     * Generate embedding vectors for each code chunk.
     *
     * Chunks are converted to a rich text representation before embedding to
     * maximise semantic quality: name + signature + docstring + content.
     *
     * Embedding calls are batched at EMBEDDING_BATCH_SIZE (100) to stay within
     * API limits. The Laravel AI SDK caches results for 1 hour automatically.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     * @return array<int, float[]>  Indexed identically to $chunks.
     */
    private function embedChunks(array $chunks): array
    {
        $texts = array_map(
            fn (array $chunk) => $this->buildEmbeddingText($chunk),
            $chunks
        );

        $allVectors = [];

        foreach (array_chunk($texts, self::EMBEDDING_BATCH_SIZE, preserve_keys: true) as $batchTexts) {
            $batchVectors = $this->embeddingService->generateBatch(array_values($batchTexts));

            foreach (array_keys($batchTexts) as $i => $originalIndex) {
                $allVectors[$originalIndex] = $batchVectors[$i] ?? [];
            }
        }

        ksort($allVectors);

        return array_values($allVectors);
    }

    /**
     * Build a rich text representation of a code chunk for embedding.
     * Combines the most semantically meaningful fields.
     */
    private function buildEmbeddingText(array $chunk): string
    {
        $parts = array_filter([
            $chunk['name']      ?? null,
            $chunk['signature'] ?? null,
            $chunk['docstring'] ?? null,
            $chunk['content']   ?? null,
        ]);

        return implode("\n", $parts);
    }

    /**
     * Upsert vector points into Qdrant, pairing each chunk with its embedding vector.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     * @param  array<int, float[]>               $vectors
     */
    private function storeVectors(string $collectionName, array $chunks, array $vectors): void
    {
        $points = [];

        foreach ($chunks as $i => $chunk) {
            $vector = $vectors[$i] ?? [];

            if (empty($vector)) {
                continue;
            }

            $points[] = [
                'id'      => Str::uuid()->toString(),
                'vector'  => $vector,
                'payload' => [
                    'file_path'  => $chunk['filePath'],
                    'chunk_type' => $chunk['type'],
                    'name'       => $chunk['name'],
                    'content'    => $chunk['content'],
                    'start_line' => $chunk['startLine'],
                    'end_line'   => $chunk['endLine'],
                    'language'   => $chunk['language'],
                    'signature'  => $chunk['signature'] ?? null,
                    'docstring'  => $chunk['docstring'] ?? null,
                ],
            ];
        }

        if (!empty($points)) {
            $this->vectorDb->upsertPoints($collectionName, $points);
        }
    }

    /**
     * Persist lightweight chunk metadata to PostgreSQL.
     * The full content lives in Qdrant; we store only what's needed for
     * referencing results (file path, lines, type, name).
     *
     * @param  array<int, array<string, mixed>>  $chunks
     */
    private function storeChunkMetadata(string $repositoryId, array $chunks): void
    {
        $records = array_map(fn (array $chunk) => [
            'id'            => Str::uuid()->toString(),
            'repository_id' => $repositoryId,
            'vector_id'     => null,
            'file_path'     => $chunk['filePath'],
            'chunk_type'    => $chunk['type'],
            'name'          => $chunk['name'],
            'start_line'    => $chunk['startLine'],
            'end_line'      => $chunk['endLine'],
            'language'      => $chunk['language'],
            'signature'     => $chunk['signature'] ?? null,
            'docstring'     => $chunk['docstring'] ?? null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $chunks);

        CodeChunk::insert($records);
    }

    /**
     * Update the repository's progress counters in the database.
     */
    private function updateProgress(Repository $repository, int $indexedFiles, int $totalChunks): void
    {
        $repository->update([
            'indexed_files' => $indexedFiles,
            'total_chunks'  => $totalChunks,
        ]);
    }
}
