<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRepositoryRequest;
use App\Http\Resources\RepositoryResource;
use App\Jobs\IndexRepositoryJob;
use App\Models\Repository;
use App\Services\VectorDBService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RepositoryController extends Controller
{
    /**
     * Maximum number of repositories allowed per user in the MVP.
     */
    private const MAX_REPOSITORIES_PER_USER = 10;

    /**
     * List all repositories for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $repositories = $request->user()
            ->repositories()
            ->latest()
            ->paginate(20);

        return RepositoryResource::collection($repositories);
    }

    /**
     * Create a new repository and dispatch the background indexing job.
     *
     * The git_token is encrypted before being stored so the plaintext
     * token never exists in the database. Decryption happens only in
     * GitManager::clone() at indexing time.
     */
    public function store(StoreRepositoryRequest $request): JsonResponse
    {
        // Enforce the 10-repository per user limit.
        $repositoryCount = $request->user()->repositories()->count();
        if ($repositoryCount >= self::MAX_REPOSITORIES_PER_USER) {
            return response()->json([
                'message' => 'You have reached the maximum of ' . self::MAX_REPOSITORIES_PER_USER . ' repositories. '
                    . 'Please delete an existing repository before adding a new one.',
            ], 422);
        }

        $validated = $request->validated();

        $repository = $request->user()->repositories()->create([
            'name'            => $validated['name'],
            'git_url'         => $validated['git_url'],
            'branch'          => $validated['branch'] ?? 'main',
            'git_token'       => isset($validated['git_token'])
                ? encrypt($validated['git_token'])
                : null,
            'indexing_status' => 'pending',
        ]);

        // Dispatch the background indexing job.
        IndexRepositoryJob::dispatch($repository);

        return (new RepositoryResource($repository))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a single repository by ID.
     */
    public function show(Request $request, Repository $repository): RepositoryResource
    {
        $this->authorize('view', $repository);

        return new RepositoryResource($repository);
    }

    /**
     * Delete a repository and its associated data.
     *
     * Removes the Qdrant vector collection first so we don't leave orphaned
     * vectors even if the DB delete fails. CodeChunk rows are cascade-deleted
     * by the database foreign key constraint.
     */
    public function destroy(Request $request, Repository $repository, VectorDBService $vectorDb): JsonResponse
    {
        $this->authorize('delete', $repository);

        $vectorDb->deleteCollection("repo_{$repository->id}");

        $repository->delete();

        return response()->json(['message' => 'Repository deleted successfully.']);
    }

    /**
     * Get the current indexing status and progress for a repository.
     */
    public function status(Request $request, Repository $repository): JsonResponse
    {
        $this->authorize('view', $repository);

        return response()->json([
            'repository_id'  => $repository->id,
            'status'         => $repository->indexing_status,
            'progress'       => $repository->indexed_files && $repository->total_files
                ? round(($repository->indexed_files / $repository->total_files) * 100, 1)
                : 0,
            'total_files'    => $repository->total_files,
            'indexed_files'  => $repository->indexed_files,
            'total_chunks'   => $repository->total_chunks,
            'started_at'     => $repository->indexing_started_at?->toIso8601String(),
            'completed_at'   => $repository->indexing_completed_at?->toIso8601String(),
            'error'          => $repository->indexing_error,
        ]);
    }

    /**
     * Reset the repository state and dispatch a fresh indexing job.
     *
     * Deletes the existing Qdrant collection and CodeChunk records so the
     * pipeline starts from a clean slate.
     */
    public function reindex(Request $request, Repository $repository, VectorDBService $vectorDb): JsonResponse
    {
        $this->authorize('update', $repository);

        $vectorDb->deleteCollection("repo_{$repository->id}");

        $repository->codeChunks()->delete();

        $repository->update([
            'indexing_status'       => 'pending',
            'indexing_started_at'   => null,
            'indexing_completed_at' => null,
            'indexing_error'        => null,
            'indexed_files'         => 0,
            'total_chunks'          => 0,
        ]);

        IndexRepositoryJob::dispatch($repository);

        return response()->json([
            'message'       => 'Re-indexing has been queued.',
            'repository_id' => $repository->id,
        ]);
    }
}
