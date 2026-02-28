<?php

namespace Tests\Feature;

use App\Jobs\IndexRepositoryJob;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RepositoryControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        // Fake the queue so jobs don't actually run during tests.
        Queue::fake();
    }

    // ── List ──────────────────────────────────────────────────────────────────

    public function test_user_can_list_their_repositories(): void
    {
        Repository::factory()->count(3)->create(['user_id' => $this->user->id]);
        // Another user's repo — should NOT appear in the response.
        Repository::factory()->create(['user_id' => User::factory()->create()->id]);

        $response = $this->actingAs($this->user)->getJson('/api/repositories');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_unauthenticated_user_cannot_list_repositories(): void
    {
        $this->getJson('/api/repositories')->assertUnauthorized();
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function test_user_can_create_a_repository(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/repositories', [
            'name'    => 'My Repo',
            'git_url' => 'https://github.com/owner/my-repo',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'My Repo');
        $response->assertJsonPath('data.indexing_status', 'pending');
        // Token must never be in the response.
        $response->assertJsonMissing(['git_token']);

        $this->assertDatabaseHas('repositories', [
            'user_id' => $this->user->id,
            'name'    => 'My Repo',
        ]);

        // Confirm the background job was dispatched.
        Queue::assertPushed(IndexRepositoryJob::class);
    }

    public function test_user_can_create_private_repository_with_token(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/repositories', [
            'name'      => 'Private Repo',
            'git_url'   => 'https://gitlab.com/owner/private-repo',
            'git_token' => 'glpat-supersecrettoken',
        ]);

        $response->assertCreated();

        $repository = Repository::find($response->json('data.id'));
        // Token must be stored encrypted (not plaintext).
        $this->assertNotEquals('glpat-supersecrettoken', $repository->git_token);
        $this->assertEquals('glpat-supersecrettoken', decrypt($repository->git_token));
    }

    public function test_user_cannot_create_more_than_ten_repositories(): void
    {
        Repository::factory()->count(10)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->postJson('/api/repositories', [
            'name'    => 'Eleventh Repo',
            'git_url' => 'https://github.com/owner/eleventh',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', fn ($msg) => str_contains($msg, 'maximum of 10 repositories'));
        Queue::assertNotPushed(IndexRepositoryJob::class);
    }

    public function test_store_rejects_invalid_git_url(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/repositories', [
            'name'    => 'Bad Repo',
            'git_url' => 'https://example.com/owner/repo',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['git_url']);
    }

    public function test_store_rejects_non_https_git_url(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/repositories', [
            'name'    => 'SSH Repo',
            'git_url' => 'git@github.com:owner/repo.git',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['git_url']);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/repositories', [
            'git_url' => 'https://github.com/owner/repo',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function test_user_can_view_their_repository(): void
    {
        $repo = Repository::factory()->completed()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson("/api/repositories/{$repo->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $repo->id);
        $response->assertJsonPath('data.indexing_status', 'completed');
    }

    public function test_user_cannot_view_another_users_repository(): void
    {
        $other = User::factory()->create();
        $repo  = Repository::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->getJson("/api/repositories/{$repo->id}")
            ->assertForbidden();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_user_can_delete_their_repository(): void
    {
        $repo = Repository::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->deleteJson("/api/repositories/{$repo->id}")
            ->assertOk();

        $this->assertDatabaseMissing('repositories', ['id' => $repo->id]);
    }

    public function test_user_cannot_delete_another_users_repository(): void
    {
        $other = User::factory()->create();
        $repo  = Repository::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->deleteJson("/api/repositories/{$repo->id}")
            ->assertForbidden();
    }

    // ── Status ────────────────────────────────────────────────────────────────

    public function test_can_get_repository_status(): void
    {
        $repo = Repository::factory()->inProgress()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/repositories/{$repo->id}/status");

        $response->assertOk();
        $response->assertJsonStructure([
            'repository_id', 'status', 'progress',
            'total_files', 'indexed_files', 'total_chunks',
            'started_at', 'completed_at', 'error',
        ]);
        $response->assertJsonPath('status', 'in_progress');
    }

    public function test_status_returns_correct_progress_percentage(): void
    {
        $repo = Repository::factory()->create([
            'user_id'         => $this->user->id,
            'indexing_status' => 'in_progress',
            'total_files'     => 200,
            'indexed_files'   => 100,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/repositories/{$repo->id}/status");

        $response->assertOk();
        $this->assertEqualsWithDelta(50.0, (float) $response->json('progress'), 0.01);
    }

    // ── Reindex ───────────────────────────────────────────────────────────────

    public function test_can_reindex_repository(): void
    {
        $repo = Repository::factory()->completed()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/repositories/{$repo->id}/reindex");

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Re-indexing has been queued.']);

        $this->assertDatabaseHas('repositories', [
            'id'              => $repo->id,
            'indexing_status' => 'pending',
        ]);

        Queue::assertPushed(IndexRepositoryJob::class);
    }

    public function test_user_cannot_reindex_another_users_repository(): void
    {
        $other = User::factory()->create();
        $repo  = Repository::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)
            ->postJson("/api/repositories/{$repo->id}/reindex")
            ->assertForbidden();
    }
}
