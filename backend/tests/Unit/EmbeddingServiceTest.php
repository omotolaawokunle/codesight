<?php

namespace Tests\Unit;

use App\Services\EmbeddingService;
use Laravel\Ai\Embeddings;
use Mockery;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    private EmbeddingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmbeddingService();
    }

    // ── getDimensions ─────────────────────────────────────────────────────────

    public function test_get_dimensions_returns_1536(): void
    {
        $this->assertSame(1536, $this->service->getDimensions());
    }

    // ── generateBatch ─────────────────────────────────────────────────────────

    public function test_generate_batch_returns_empty_for_empty_input(): void
    {
        $result = $this->service->generateBatch([]);
        $this->assertSame([], $result);
    }

    public function test_generate_batch_returns_empty_arrays_on_api_failure(): void
    {
        // Force the static Embeddings facade to throw.
        Mockery::mock('alias:Laravel\Ai\Embeddings')
            ->shouldReceive('for')->andThrow(new \RuntimeException('API error'));

        $result = $this->service->generateBatch(['some code snippet']);

        // On failure the service returns array_fill(0, count($texts), [])
        $this->assertCount(1, $result);
        $this->assertSame([], $result[0]);
    }

    // ── generateEmbedding ─────────────────────────────────────────────────────

    public function test_generate_embedding_returns_empty_array_on_failure(): void
    {
        Mockery::mock('alias:Laravel\Ai\Embeddings')
            ->shouldReceive('for')->andThrow(new \RuntimeException('API error'));

        $result = $this->service->generateEmbedding('hello world');

        $this->assertSame([], $result);
    }
}
