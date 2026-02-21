<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RepositoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $repositories = $request->user()
            ->repositories()
            ->latest()
            ->paginate(20);

        return response()->json($repositories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'git_url' => ['required', 'string', 'url'],
            'branch' => ['nullable', 'string', 'max:100'],
            'git_token' => ['nullable', 'string'],
        ]);

        $repository = $request->user()->repositories()->create([
            'name' => $validated['name'],
            'git_url' => $validated['git_url'],
            'branch' => $validated['branch'] ?? 'main',
            'git_token' => $validated['git_token'] ?? null,
            'indexing_status' => 'pending',
        ]);

        return response()->json($repository, 201);
    }

    public function show(Request $request, Repository $repository): JsonResponse
    {
        $this->authorize('view', $repository);

        return response()->json($repository);
    }

    public function destroy(Request $request, Repository $repository): JsonResponse
    {
        $this->authorize('delete', $repository);

        $repository->delete();

        return response()->json(['message' => 'Repository deleted successfully']);
    }

    public function status(Repository $repository): JsonResponse
    {
        $this->authorize('view', $repository);

        return response()->json([
            'repository_id' => $repository->id,
            'status' => $repository->indexing_status,
            'progress' => $repository->indexed_files && $repository->total_files
                ? round(($repository->indexed_files / $repository->total_files) * 100, 1)
                : 0,
            'total_files' => $repository->total_files,
            'indexed_files' => $repository->indexed_files,
            'total_chunks' => $repository->total_chunks,
            'started_at' => $repository->indexing_started_at,
            'completed_at' => $repository->indexing_completed_at,
            'error' => $repository->indexing_error,
        ]);
    }

    public function reindex(Request $request, Repository $repository): JsonResponse
    {
        $this->authorize('update', $repository);

        $repository->update([
            'indexing_status' => 'pending',
            'indexing_started_at' => null,
            'indexing_completed_at' => null,
            'indexing_error' => null,
            'indexed_files' => 0,
            'total_chunks' => 0,
        ]);

        return response()->json([
            'message' => 'Re-indexing started',
            'repository_id' => $repository->id,
        ]);
    }
}
