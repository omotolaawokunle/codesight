<?php

namespace Tests\Unit;

use App\Services\VectorDBService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class VectorDBServiceTest extends TestCase
{
    private VectorDBService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VectorDBService();
    }

    // ── createCollection ─────────────────────────────────────────────────────

    public function test_create_collection_succeeds_on_200(): void
    {
        Http::fake([
            '*/collections/repo_1' => Http::response(['result' => true, 'status' => 'ok'], 200),
        ]);

        $this->service->createCollection('repo_1');

        Http::assertSent(fn ($req) => str_contains($req->url(), '/collections/repo_1'));
    }

    public function test_create_collection_ignores_409_conflict(): void
    {
        Http::fake([
            '*/collections/repo_1' => Http::response(['status' => 'error', 'error' => 'already exists'], 409),
        ]);

        // Should not throw
        $this->service->createCollection('repo_1');
        $this->assertTrue(true);
    }

    public function test_create_collection_throws_on_server_error(): void
    {
        Http::fake([
            '*/collections/repo_1' => Http::response(['status' => 'error'], 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed to create qdrant collection/i');

        $this->service->createCollection('repo_1');
    }

    // ── upsertPoints ─────────────────────────────────────────────────────────

    public function test_upsert_points_sends_correct_payload(): void
    {
        Http::fake([
            '*/collections/repo_1/points' => Http::response(['result' => ['operation_id' => 1, 'status' => 'completed']], 200),
        ]);

        $points = [
            ['id' => 'uuid-1', 'vector' => array_fill(0, 1536, 0.1), 'payload' => ['file_path' => 'src/foo.php']],
        ];

        $this->service->upsertPoints('repo_1', $points);

        Http::assertSent(function ($req) {
            $body = $req->data();
            return isset($body['points']) && count($body['points']) === 1;
        });
    }

    public function test_upsert_points_is_noop_for_empty_array(): void
    {
        Http::fake();

        $this->service->upsertPoints('repo_1', []);

        Http::assertNothingSent();
    }

    public function test_upsert_points_throws_on_failure(): void
    {
        Http::fake([
            '*/collections/repo_1/points' => Http::response('Bad request', 400),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches("/failed to upsert points/i");

        $this->service->upsertPoints('repo_1', [
            ['id' => 'uuid-1', 'vector' => array_fill(0, 1536, 0.1), 'payload' => []],
        ]);
    }

    // ── search ────────────────────────────────────────────────────────────────

    public function test_search_returns_result_array(): void
    {
        $results = [
            ['id' => 'uuid-1', 'score' => 0.92, 'payload' => ['file_path' => 'src/foo.php', 'content' => 'hello']],
            ['id' => 'uuid-2', 'score' => 0.80, 'payload' => ['file_path' => 'src/bar.php', 'content' => 'world']],
        ];

        Http::fake([
            '*/points/search' => Http::response(['result' => $results], 200),
        ]);

        $vector = array_fill(0, 1536, 0.1);
        $found  = $this->service->search('repo_1', $vector, 5, 0.75);

        $this->assertCount(2, $found);
        $this->assertEquals('uuid-1', $found[0]['id']);
    }

    public function test_search_returns_empty_array_on_failure(): void
    {
        Http::fake([
            '*/points/search' => Http::response('error', 500),
        ]);

        $result = $this->service->search('repo_1', array_fill(0, 1536, 0.1));

        $this->assertSame([], $result);
    }

    // ── scrollByFilter ────────────────────────────────────────────────────────

    public function test_scroll_by_filter_returns_points(): void
    {
        $points = [
            ['id' => 'uuid-1', 'payload' => ['file_path' => 'src/foo.php']],
        ];

        Http::fake([
            '*/points/scroll' => Http::response(['result' => ['points' => $points]], 200),
        ]);

        $result = $this->service->scrollByFilter('repo_1', 'file_path', 'src/foo.php');

        $this->assertCount(1, $result);
    }

    public function test_scroll_by_filter_returns_empty_on_failure(): void
    {
        Http::fake([
            '*/points/scroll' => Http::response('error', 500),
        ]);

        $result = $this->service->scrollByFilter('repo_1', 'file_path', 'src/foo.php');

        $this->assertSame([], $result);
    }

    // ── deleteCollection ──────────────────────────────────────────────────────

    public function test_delete_collection_succeeds(): void
    {
        Http::fake([
            '*/collections/repo_1' => Http::response(['result' => true], 200),
        ]);

        $this->service->deleteCollection('repo_1');

        Http::assertSent(fn ($req) => $req->method() === 'DELETE');
    }

    public function test_delete_collection_silently_ignores_404(): void
    {
        Http::fake([
            '*/collections/repo_1' => Http::response('not found', 404),
        ]);

        // Should not throw
        $this->service->deleteCollection('repo_1');
        $this->assertTrue(true);
    }

    // ── health ────────────────────────────────────────────────────────────────

    public function test_health_returns_true_when_reachable(): void
    {
        Http::fake([
            '*' => Http::response('ok', 200),
        ]);

        $this->assertTrue($this->service->health());
    }

    public function test_health_returns_false_on_server_error(): void
    {
        Http::fake([
            '*' => Http::response('error', 500),
        ]);

        $this->assertFalse($this->service->health());
    }

    public function test_health_returns_false_on_connection_exception(): void
    {
        Http::fake([
            '*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('refused'),
        ]);

        $this->assertFalse($this->service->health());
    }
}
