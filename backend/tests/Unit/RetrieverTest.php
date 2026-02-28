<?php

namespace Tests\Unit;

use App\Services\EmbeddingService;
use App\Services\Retriever;
use App\Services\VectorDBService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class RetrieverTest extends TestCase
{
    use RefreshDatabase;

    private EmbeddingService $embedding;
    private VectorDBService $vectorDb;
    private Retriever $retriever;

    protected function setUp(): void
    {
        parent::setUp();

        $this->embedding = Mockery::mock(EmbeddingService::class);
        $this->vectorDb  = Mockery::mock(VectorDBService::class);
        $this->retriever = new Retriever($this->embedding, $this->vectorDb);

        // Bypass cache so tests always exercise the underlying service logic.
        Cache::shouldReceive('remember')->andReturnUsing(fn ($key, $ttl, $cb) => $cb());
        Cache::shouldReceive('store')->andReturnSelf();
        Cache::shouldReceive('flush')->andReturn(true);
    }

    // ── retrieveRelevantChunks ────────────────────────────────────────────────

    public function test_retrieve_relevant_chunks_returns_formatted_results(): void
    {
        $vector = array_fill(0, 1536, 0.5);

        $this->embedding->shouldReceive('generateEmbedding')
            ->once()
            ->with('how does auth work')
            ->andReturn($vector);

        $this->vectorDb->shouldReceive('search')
            ->once()
            ->andReturn([
                ['id' => 'abc', 'score' => 0.95, 'payload' => [
                    'file_path'  => 'src/Auth.php',
                    'chunk_type' => 'function',
                    'name'       => 'authenticate',
                    'content'    => '<?php function authenticate() {}',
                    'start_line' => 10,
                    'end_line'   => 20,
                    'language'   => 'php',
                ]],
            ]);

        $results = $this->retriever->retrieveRelevantChunks(1, 'how does auth work');

        $this->assertCount(1, $results);
        $this->assertEquals('src/Auth.php', $results[0]['file_path']);
        $this->assertEquals(0.95, $results[0]['score']);
        $this->assertEquals('authenticate', $results[0]['name']);
    }

    public function test_retrieve_relevant_chunks_returns_empty_when_embedding_is_empty(): void
    {
        $this->embedding->shouldReceive('generateEmbedding')->andReturn([]);
        $this->vectorDb->shouldReceive('search')->never();

        $results = $this->retriever->retrieveRelevantChunks(1, 'some query');

        $this->assertSame([], $results);
    }

    public function test_retrieve_relevant_chunks_caps_top_k_at_50(): void
    {
        $this->embedding->shouldReceive('generateEmbedding')->andReturn(array_fill(0, 1536, 0.5));

        $this->vectorDb->shouldReceive('search')
            ->once()
            ->withArgs(function ($collection, $vector, $limit, $threshold) {
                return $limit === 50; // capped from 100
            })
            ->andReturn([]);

        $this->retriever->retrieveRelevantChunks(1, 'query', 100);
    }

    public function test_retrieve_relevant_chunks_results_are_sorted_by_score_desc(): void
    {
        $this->embedding->shouldReceive('generateEmbedding')->andReturn(array_fill(0, 1536, 0.5));

        $this->vectorDb->shouldReceive('search')->andReturn([
            ['id' => 'b', 'score' => 0.70, 'payload' => ['file_path' => 'b.php']],
            ['id' => 'a', 'score' => 0.95, 'payload' => ['file_path' => 'a.php']],
        ]);

        $results = $this->retriever->retrieveRelevantChunks(1, 'query');

        $this->assertEquals(0.95, $results[0]['score']);
        $this->assertEquals(0.70, $results[1]['score']);
    }

    // ── hybridSearch ──────────────────────────────────────────────────────────

    public function test_hybrid_search_boosts_chunks_with_matching_name(): void
    {
        $vector = array_fill(0, 1536, 0.5);

        $this->embedding->shouldReceive('generateEmbedding')->andReturn($vector);

        $this->vectorDb->shouldReceive('search')->andReturn([
            ['id' => 'x', 'score' => 0.80, 'payload' => [
                'file_path' => 'src/auth/authenticate.php',
                'name'      => 'authenticate',
                'content'   => '',
            ]],
            ['id' => 'y', 'score' => 0.82, 'payload' => [
                'file_path' => 'src/other.php',
                'name'      => 'unrelated',
                'content'   => '',
            ]],
        ]);

        // "authenticate" is in the query, so the first chunk should be boosted above the second.
        $results = $this->retriever->hybridSearch(1, 'how does authenticate work', 2);

        $this->assertCount(2, $results);
        // The chunk named 'authenticate' should rank first after boost.
        $this->assertEquals('authenticate', $results[0]['name']);
    }

    public function test_hybrid_search_returns_empty_when_no_chunks_found(): void
    {
        $this->embedding->shouldReceive('generateEmbedding')->andReturn(array_fill(0, 1536, 0.5));
        $this->vectorDb->shouldReceive('search')->andReturn([]);

        $result = $this->retriever->hybridSearch(1, 'anything');

        $this->assertSame([], $result);
    }

    // ── retrieveByFilePath ────────────────────────────────────────────────────

    public function test_retrieve_by_file_path_calls_scroll_and_normalises(): void
    {
        $this->vectorDb->shouldReceive('scrollByFilter')
            ->once()
            ->with('repo_1', 'file_path', 'src/User.php')
            ->andReturn([
                ['id' => 'u1', 'payload' => ['file_path' => 'src/User.php', 'name' => 'User']],
            ]);

        $results = $this->retriever->retrieveByFilePath(1, 'src/User.php');

        $this->assertCount(1, $results);
        $this->assertEquals('src/User.php', $results[0]['file_path']);
        $this->assertEquals(1.0, $results[0]['score']);
    }

    // ── retrieveByErrorTrace ──────────────────────────────────────────────────

    public function test_retrieve_by_error_trace_parses_python_stack(): void
    {
        $this->embedding->shouldReceive('generateEmbedding')->andReturn(array_fill(0, 1536, 0.5));
        $this->vectorDb->shouldReceive('search')->andReturn([]);
        $this->vectorDb->shouldReceive('scrollByFilter')->andReturn([]);

        $errorLog = <<<'ERR'
        Traceback (most recent call last):
          File "app.py", line 42, in process_request
            result = db.query(sql)
        AttributeError: 'NoneType' object has no attribute 'query'
        ERR;

        $results = $this->retriever->retrieveByErrorTrace(1, $errorLog);

        // With no matching DB records, results may be empty but no exception thrown.
        $this->assertIsArray($results);
    }

    public function test_retrieve_by_error_trace_parses_javascript_stack(): void
    {
        $this->embedding->shouldReceive('generateEmbedding')->andReturn(array_fill(0, 1536, 0.5));
        $this->vectorDb->shouldReceive('search')->andReturn([]);
        $this->vectorDb->shouldReceive('scrollByFilter')->andReturn([]);

        $errorLog = <<<'ERR'
        TypeError: Cannot read properties of undefined (reading 'map')
            at processItems (src/utils.js:15:22)
            at main (src/index.js:8:3)
        ERR;

        $results = $this->retriever->retrieveByErrorTrace(1, $errorLog);

        $this->assertIsArray($results);
    }

    // ── formatContextForLLM ───────────────────────────────────────────────────

    public function test_format_context_for_llm_returns_string(): void
    {
        $chunks = [
            [
                'file_path'  => 'src/Auth.php',
                'start_line' => 1,
                'end_line'   => 10,
                'language'   => 'php',
                'content'    => '<?php echo "hello";',
            ],
        ];

        $output = $this->retriever->formatContextForLLM($chunks);

        $this->assertStringContainsString('src/Auth.php', $output);
        $this->assertStringContainsString('```php', $output);
        $this->assertStringContainsString('echo "hello"', $output);
    }

    public function test_format_context_for_llm_respects_token_budget(): void
    {
        // Create 100 chunks each with 1000 chars of content.
        $chunks = [];
        for ($i = 0; $i < 100; $i++) {
            $chunks[] = [
                'file_path'  => "src/File{$i}.php",
                'start_line' => 1,
                'end_line'   => 10,
                'language'   => 'php',
                'content'    => str_repeat('x', 1000),
            ];
        }

        // maxTokens of 100 → budget of 400 chars → only a few chunks should fit.
        $output = $this->retriever->formatContextForLLM($chunks, 100);

        $this->assertLessThan(count($chunks), substr_count($output, '```'));
    }

    public function test_format_context_for_llm_returns_empty_string_for_no_chunks(): void
    {
        $output = $this->retriever->formatContextForLLM([]);

        $this->assertSame('', $output);
    }
}
