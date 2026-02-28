<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VectorDBService
{
    private const VECTOR_SIZE = 1536;
    private const DISTANCE = 'Cosine';

    private string $baseUrl;

    public function __construct()
    {
        $host   = config('services.qdrant.host', '127.0.0.1');
        $port   = config('services.qdrant.port', 6333);

        $this->baseUrl = "http://{$host}:{$port}";
    }

    /**
     * Create a Qdrant collection for the given repository if it does not exist.
     * Uses COSINE distance with 1536-dimensional vectors.
     */
    public function createCollection(string $collectionName): void
    {
        $response = $this->client()->put("/collections/{$collectionName}", [
            'vectors' => [
                'size'     => self::VECTOR_SIZE,
                'distance' => self::DISTANCE,
            ],
        ]);

        if ($response->failed() && $response->status() !== 409) {
            throw new RuntimeException(
                "Failed to create Qdrant collection '{$collectionName}': {$response->body()}"
            );
        }

        Log::info('VectorDBService: collection ready', ['collection' => $collectionName]);
    }

    /**
     * Upsert vector points into a Qdrant collection.
     *
     * Each point must have:
     *   - id (string UUID)
     *   - vector (float[])
     *   - payload (associative array of metadata)
     *
     * @param  array<int, array{id: string, vector: float[], payload: array<string, mixed>}>  $points
     */
    public function upsertPoints(string $collectionName, array $points): void
    {
        if (empty($points)) {
            return;
        }

        $response = $this->client()->put("/collections/{$collectionName}/points", [
            'points' => $points,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Failed to upsert points into '{$collectionName}': {$response->body()}"
            );
        }
    }

    /**
     * Perform a similarity search against a Qdrant collection.
     *
     * @param  float[]  $vector
     * @return array<int, array{id: string, score: float, payload: array<string, mixed>}>
     */
    public function search(string $collectionName, array $vector, int $limit = 10, float $scoreThreshold = 0.7): array
    {
        $response = $this->client()->post("/collections/{$collectionName}/points/search", [
            'vector'          => $vector,
            'limit'           => $limit,
            'score_threshold' => $scoreThreshold,
            'with_payload'    => true,
        ]);

        if ($response->failed()) {
            Log::warning('VectorDBService: search failed', [
                'collection' => $collectionName,
                'status'     => $response->status(),
                'body'       => $response->body(),
            ]);

            return [];
        }

        return $response->json('result', []);
    }

    /**
     * Delete a Qdrant collection and all its vectors.
     * Silently ignores 404 (collection does not exist).
     */
    public function deleteCollection(string $collectionName): void
    {
        $response = $this->client()->delete("/collections/{$collectionName}");

        if ($response->failed() && $response->status() !== 404) {
            Log::warning('VectorDBService: failed to delete collection', [
                'collection' => $collectionName,
                'status'     => $response->status(),
            ]);
        } else {
            Log::info('VectorDBService: collection deleted', ['collection' => $collectionName]);
        }
    }

    /**
     * Check whether the Qdrant instance is reachable.
     */
    public function health(): bool
    {
        try {
            return $this->client()->timeout(5)->get('/')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Build the HTTP client with shared headers and timeout.
     */
    private function client(): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl)
            ->timeout(30)
            ->acceptJson()
            ->contentType('application/json');

        $apiKey = config('services.qdrant.api_key');
        if ($apiKey) {
            $client = $client->withHeaders(['api-key' => $apiKey]);
        }

        return $client;
    }
}
