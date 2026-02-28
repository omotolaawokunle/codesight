<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ASTServiceClient
{
    private string $baseUrl;

    private const SUPPORTED_EXTENSIONS = [
        'py'  => 'python',
        'js'  => 'javascript',
        'jsx' => 'javascript',
        'ts'  => 'typescript',
        'tsx' => 'typescript',
        'java' => 'java',
        'php' => 'php',
    ];

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ast_service.url', 'http://localhost:3001'), '/');
    }

    /**
     * Parse a single file and return its code chunks.
     *
     * @return array<int, array{type: string, name: string, content: string, startLine: int, endLine: int, language: string, signature: ?string, docstring: ?string}>
     */
    public function parse(string $filePath, string $content, string $language): array
    {
        $result = $this->parseBatch([
            ['filePath' => $filePath, 'content' => $content, 'language' => $language],
        ]);

        return $result[0]['chunks'] ?? [];
    }

    /**
     * Parse multiple files in a single request to the AST service.
     *
     * @param  array<int, array{filePath: string, content: string, language: string}>  $files
     * @return array<int, array{success: bool, filePath: string, language: string, chunks: array, chunkCount: int, error: ?string}>
     */
    public function parseBatch(array $files): array
    {
        if (empty($files)) {
            return [];
        }

        try {
            $response = Http::timeout(30)
                ->retry(3, 500, fn ($exception) => $exception instanceof ConnectionException)
                ->post("{$this->baseUrl}/api/ast/parse-batch", ['files' => $files]);

            if ($response->failed()) {
                Log::warning('ASTServiceClient: batch parse failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'files'  => array_column($files, 'filePath'),
                ]);

                return $this->emptyResultsFor($files);
            }

            $data = $response->json();

            return $data['results'] ?? $this->emptyResultsFor($files);

        } catch (\Throwable $e) {
            Log::error('ASTServiceClient: request exception', [
                'error' => $e->getMessage(),
                'files' => array_column($files, 'filePath'),
            ]);

            return $this->emptyResultsFor($files);
        }
    }

    /**
     * Detect the language name from a file path's extension.
     * Returns null for unsupported extensions.
     */
    public function detectLanguage(string $filePath): ?string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return self::SUPPORTED_EXTENSIONS[$extension] ?? null;
    }

    /**
     * Check whether the AST service is reachable and healthy.
     */
    public function health(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Build empty parse results for each file in a batch (used on failure).
     *
     * @param  array<int, array{filePath: string, content: string, language: string}>  $files
     * @return array<int, array{success: bool, filePath: string, language: string, chunks: array, chunkCount: int, error: ?string}>
     */
    private function emptyResultsFor(array $files): array
    {
        return array_map(fn ($file) => [
            'success'    => false,
            'filePath'   => $file['filePath'],
            'language'   => $file['language'],
            'chunks'     => [],
            'chunkCount' => 0,
            'error'      => 'AST service unavailable or returned an error',
        ], $files);
    }
}
