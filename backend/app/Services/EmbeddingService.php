<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

class EmbeddingService
{
    private const MODEL = 'gemini-embedding-001';
    private const DIMENSIONS = 1536;
    private const CACHE_SECONDS = 3600;
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

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
     * Generate embedding vectors for multiple texts using Gemini's
     * batchEmbedContents endpoint, with per-text caching.
     *
     * @param  string[]  $texts
     * @return float[][] Indexed the same as $texts; empty arrays on error.
     */
    public function generateBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        if (config('ai.default_for_embeddings') === 'gemini') {
            return $this->generateBatchWithGemini($texts);
        }

        try {
            $response = Embeddings::for($texts)
                ->dimensions(self::DIMENSIONS)
                ->cache(self::CACHE_SECONDS)
                ->generate();

            return $response->embeddings;
        } catch (Throwable $e) {
            Log::error('EmbeddingService: failed to generate embeddings', [
                'error' => $e->getMessage(),
                'text_count' => count($texts),
            ]);

            return array_fill(0, count($texts), []);
        }
    }

    private function generateBatchWithGemini(array $texts): array
    {
        $results   = array_fill(0, count($texts), []);
        $uncached  = [];

        foreach ($texts as $i => $text) {
            $cached = Cache::get($this->cacheKey($text));
            if ($cached !== null) {
                $results[$i] = $cached;
            } else {
                $uncached[$i] = $text;
            }
        }

        if (empty($uncached)) {
            return $results;
        }

        try {
            $apiKey   = config('services.gemini.api_key') ?? env('GEMINI_API_KEY');
            $model    = self::MODEL;
            $requests = array_values(array_map(fn(string $text) => [
                'model'   => "models/{$model}",
                'content' => ['parts' => [['text' => $text]]],
                'outputDimensionality' => self::DIMENSIONS,
            ], $uncached));

            $response = Http::withQueryParameters(['key' => $apiKey])
                ->timeout(60)
                ->post(self::API_BASE . "/models/{$model}:batchEmbedContents", [
                    'requests' => $requests,
                ]);

            if ($response->failed()) {
                Log::error('EmbeddingService: batchEmbedContents failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'count'  => count($uncached),
                ]);

                return $results;
            }

            $embeddings = $response->json('embeddings', []);

            foreach (array_keys($uncached) as $j => $originalIndex) {
                $vector = $embeddings[$j]['values'] ?? [];
                $results[$originalIndex] = $vector;

                if (!empty($vector)) {
                    Cache::put($this->cacheKey($texts[$originalIndex]), $vector, self::CACHE_SECONDS);
                }
            }
        } catch (Throwable $e) {
            Log::error('EmbeddingService: batch request exception', [
                'error' => $e->getMessage(),
                'count' => count($uncached),
            ]);
        }

        return $results;
    }

    public function getDimensions(): int
    {
        return self::DIMENSIONS;
    }

    private function cacheKey(string $text): string
    {
        return 'embedding:' . md5(self::MODEL . ':' . self::DIMENSIONS . ':' . $text);
    }
}
