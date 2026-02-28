<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Repository;
use App\Models\User;
use App\Services\LLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Repository $repo;
    private LLMService $llm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->repo = Repository::factory()->completed()->create(['user_id' => $this->user->id]);

        // Mock LLMService to avoid hitting real AI APIs.
        $this->llm = Mockery::mock(LLMService::class);
        $this->app->instance(LLMService::class, $this->llm);
    }

    // ── POST /api/chat ────────────────────────────────────────────────────────

    public function test_user_can_send_a_chat_query_and_gets_a_response(): void
    {
        $this->llm->shouldReceive('generateTitle')->once()->andReturn('How does auth work?');
        $this->llm->shouldReceive('chat')->once()->andReturn([
            'content' => 'The auth system uses Sanctum tokens.',
            'sources' => [['file_path' => 'app/Http/Controllers/AuthController.php', 'score' => 0.95]],
            'usage'   => ['input_tokens' => 100, 'output_tokens' => 50],
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/chat', [
            'repository_id' => $this->repo->id,
            'query'         => 'How does auth work?',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['conversation_id', 'content', 'sources', 'usage']);
        $response->assertJsonPath('content', 'The auth system uses Sanctum tokens.');

        $this->assertDatabaseHas('conversations', ['repository_id' => $this->repo->id, 'user_id' => $this->user->id]);
    }

    public function test_chat_appends_message_to_existing_conversation(): void
    {
        $conversation = Conversation::factory()->create([
            'user_id'       => $this->user->id,
            'repository_id' => $this->repo->id,
        ]);

        $this->llm->shouldReceive('chat')->once()->andReturn([
            'content' => 'Follow-up answer.',
            'sources' => [],
            'usage'   => [],
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/chat', [
            'repository_id'   => $this->repo->id,
            'query'           => 'Follow-up question?',
            'conversation_id' => $conversation->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('conversation_id', $conversation->id);

        // Two messages should exist: user + assistant.
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_chat_returns_422_when_repository_is_not_indexed(): void
    {
        $pendingRepo = Repository::factory()->create([
            'user_id'         => $this->user->id,
            'indexing_status' => 'pending',
        ]);

        $this->llm->shouldNotReceive('chat');

        $response = $this->actingAs($this->user)->postJson('/api/chat', [
            'repository_id' => $pendingRepo->id,
            'query'         => 'What is this repo?',
        ]);

        $response->assertUnprocessable();
    }

    public function test_chat_returns_403_for_another_users_repository(): void
    {
        $other    = User::factory()->create();
        $otherRepo = Repository::factory()->completed()->create(['user_id' => $other->id]);

        $response = $this->actingAs($this->user)->postJson('/api/chat', [
            'repository_id' => $otherRepo->id,
            'query'         => 'Sneaky question',
        ]);

        $response->assertForbidden();
    }

    public function test_chat_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/chat', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['repository_id', 'query']);
    }

    public function test_chat_validates_query_max_length(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/chat', [
            'repository_id' => $this->repo->id,
            'query'         => str_repeat('x', 2001),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['query']);
    }

    public function test_unauthenticated_user_cannot_send_chat_query(): void
    {
        $this->postJson('/api/chat', [
            'repository_id' => $this->repo->id,
            'query'         => 'Hello?',
        ])->assertUnauthorized();
    }

    // ── GET /api/chat/{repositoryId}/conversations ────────────────────────────

    public function test_user_can_list_conversations_for_their_repository(): void
    {
        Conversation::factory()->count(3)->create([
            'user_id'       => $this->user->id,
            'repository_id' => $this->repo->id,
        ]);

        // Another user's conversation — should not appear.
        Conversation::factory()->create([
            'user_id'       => User::factory()->create()->id,
            'repository_id' => $this->repo->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/chat/{$this->repo->id}/conversations");

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_cannot_list_conversations_for_another_users_repo(): void
    {
        $other    = User::factory()->create();
        $otherRepo = Repository::factory()->completed()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->getJson("/api/chat/{$otherRepo->id}/conversations")
            ->assertForbidden();
    }

    // ── DELETE /api/chat/conversations/{id} ───────────────────────────────────

    public function test_user_can_delete_their_own_conversation(): void
    {
        $conversation = Conversation::factory()->create([
            'user_id'       => $this->user->id,
            'repository_id' => $this->repo->id,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/chat/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Conversation deleted.');

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
    }

    public function test_user_cannot_delete_another_users_conversation(): void
    {
        $other        = User::factory()->create();
        $conversation = Conversation::factory()->create([
            'user_id'       => $other->id,
            'repository_id' => $this->repo->id,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/chat/conversations/{$conversation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('conversations', ['id' => $conversation->id]);
    }

    // ── POST /api/chat/analyze-error ──────────────────────────────────────────

    public function test_user_can_analyze_an_error_log(): void
    {
        $this->llm->shouldReceive('analyzeError')->once()->andReturn([
            'content' => 'The error is caused by a null pointer.',
            'sources' => [],
            'usage'   => [],
        ]);

        $errorLog = "TypeError: Cannot read property 'map' of undefined\n    at processItems (src/utils.js:15:22)";

        $response = $this->actingAs($this->user)->postJson('/api/chat/analyze-error', [
            'repository_id' => $this->repo->id,
            'error_log'     => $errorLog,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['conversation_id', 'content', 'sources', 'usage']);
        $response->assertJsonPath('content', 'The error is caused by a null pointer.');
    }

    public function test_analyze_error_requires_error_log_field(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/chat/analyze-error', [
            'repository_id' => $this->repo->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['error_log']);
    }

    public function test_analyze_error_returns_422_for_non_indexed_repository(): void
    {
        $pending = Repository::factory()->create([
            'user_id'         => $this->user->id,
            'indexing_status' => 'pending',
        ]);

        $this->llm->shouldNotReceive('analyzeError');

        $this->actingAs($this->user)->postJson('/api/chat/analyze-error', [
            'repository_id' => $pending->id,
            'error_log'     => 'some error',
        ])->assertUnprocessable();
    }
}
