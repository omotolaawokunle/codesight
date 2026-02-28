<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Enums\Lab;
use Throwable;

class EmbeddingService
{
    private const MODEL = 'gemini-embedding-001';
    private const DIMENSIONS = 1536;
    private const CACHE_SECONDS = 3600;

    /**
     * Generate an embedding vector for a single text string.
     *
     * @return float[]
     */
    public function generateEmbedding(string $text): array
    {
        $results = $this->generateBatch([$text]);

        return $results[0] ?? [];
    }

    /**
     * Generate embedding vectors for multiple texts.
     *
     * The Laravel AI SDK handles caching automatically — repeated calls for the
     * same text will return cached vectors without hitting the Gemini API.
     *
     * @param  string[]  $texts
     * @return float[][] Indexed the same as $texts; empty arrays on error.
     */
    public function generateBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        try {
            $response = Embeddings::for($texts)
                ->dimensions(self::DIMENSIONS)
                ->cache(self::CACHE_SECONDS)
                ->generate(Lab::Gemini, self::MODEL);

            return $response->embeddings;

        } catch (Throwable $e) {
            Log::error('EmbeddingService: failed to generate embeddings', [
                'error'      => $e->getMessage(),
                'text_count' => count($texts),
            ]);

            return array_fill(0, count($texts), []);
        }
    }

    public function getDimensions(): int
    {
        return self::DIMENSIONS;
    }
}
