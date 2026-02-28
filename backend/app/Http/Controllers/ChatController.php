<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeErrorRequest;
use App\Http\Requests\ChatQueryRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Repository;
use App\Services\LLMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Throwable;

class ChatController extends Controller
{
    public function __construct(private readonly LLMService $llmService) {}

    /**
     * Handle a RAG-based chat query and return the full AI response.
     *
     * POST /api/chat
     */
    public function query(ChatQueryRequest $request): JsonResponse
    {
        $repositoryId   = $request->integer('repository_id');
        $query          = $request->string('query')->toString();
        $conversationId = $request->integer('conversation_id') ?: null;

        $repository = Repository::findOrFail($repositoryId);
        Gate::authorize('view', $repository);

        if ($repository->indexing_status !== 'completed') {
            return response()->json([
                'message' => 'Repository is not fully indexed yet. Please wait for indexing to complete.',
            ], 422);
        }

        $conversation = $this->findOrCreateConversation($repository, $conversationId, $query);

        $this->saveMessage($conversation->id, 'user', $query);

        try {
            $result = $this->llmService->chat(
                conversationId: $conversation->id,
                repositoryId: $repositoryId,
                query: $query,
            );

            $this->saveMessage($conversation->id, 'assistant', $result['content'], [
                'sources' => $result['sources'],
                'usage'   => $result['usage'],
            ]);

            return response()->json([
                'conversation_id' => $conversation->id,
                'content'         => $result['content'],
                'sources'         => $result['sources'],
                'usage'           => $result['usage'],
            ]);
        } catch (Throwable $e) {
            Log::error('ChatController: chat query failed', [
                'repository_id'   => $repositoryId,
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to generate a response. Please try again.',
            ], 500);
        }
    }

    /**
     * Handle a RAG-based chat query and stream the AI response using SSE.
     *
     * POST /api/chat/stream
     */
    public function stream(ChatQueryRequest $request): mixed
    {
        $repositoryId   = $request->integer('repository_id');
        $query          = $request->string('query')->toString();
        $conversationId = $request->integer('conversation_id') ?: null;

        $repository = Repository::findOrFail($repositoryId);
        Gate::authorize('view', $repository);

        if ($repository->indexing_status !== 'completed') {
            return response()->json([
                'message' => 'Repository is not fully indexed yet. Please wait for indexing to complete.',
            ], 422);
        }

        $conversation = $this->findOrCreateConversation($repository, $conversationId, $query);

        $this->saveMessage($conversation->id, 'user', $query);

        $conversationId = $conversation->id;

        try {
            $streamable = $this->llmService->stream(
                conversationId: $conversationId,
                repositoryId: $repositoryId,
                query: $query,
            );

            return $streamable->then(function (StreamedAgentResponse $response) use ($conversationId) {
                $this->saveMessage($conversationId, 'assistant', $response->text, [
                    'usage' => $response->usage?->toArray() ?? [],
                ]);
            });
        } catch (Throwable $e) {
            Log::error('ChatController: stream failed', [
                'repository_id'   => $repositoryId,
                'conversation_id' => $conversationId,
                'error'           => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to start streaming response. Please try again.',
            ], 500);
        }
    }

    /**
     * Analyse an error log against the repository's indexed code.
     *
     * POST /api/chat/analyze-error
     */
    public function analyzeError(AnalyzeErrorRequest $request): JsonResponse
    {
        $repositoryId = $request->integer('repository_id');
        $errorLog     = $request->string('error_log')->toString();

        $repository = Repository::findOrFail($repositoryId);
        Gate::authorize('view', $repository);

        if ($repository->indexing_status !== 'completed') {
            return response()->json([
                'message' => 'Repository is not fully indexed yet. Please wait for indexing to complete.',
            ], 422);
        }

        try {
            $result = $this->llmService->analyzeError($repositoryId, $errorLog);

            $title = 'Error Analysis: ' . mb_substr(trim(explode("\n", $errorLog)[0]), 0, 50);

            $conversation = Conversation::create([
                'user_id'       => $request->user()->id,
                'repository_id' => $repositoryId,
                'title'         => $title,
            ]);

            $this->saveMessage($conversation->id, 'user', "Analyze this error:\n\n{$errorLog}");
            $this->saveMessage($conversation->id, 'assistant', $result['content'], [
                'sources' => $result['sources'],
                'usage'   => $result['usage'],
            ]);

            return response()->json([
                'conversation_id' => $conversation->id,
                'content'         => $result['content'],
                'sources'         => $result['sources'],
                'usage'           => $result['usage'],
            ]);
        } catch (Throwable $e) {
            Log::error('ChatController: error analysis failed', [
                'repository_id' => $repositoryId,
                'error'         => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to analyze the error. Please try again.',
            ], 500);
        }
    }

    /**
     * List conversations for a repository belonging to the authenticated user.
     *
     * GET /api/chat/{repositoryId}/conversations
     */
    public function conversations(Request $request, int $repositoryId): JsonResponse
    {
        $repository = Repository::findOrFail($repositoryId);
        Gate::authorize('view', $repository);

        $conversations = Conversation::where('user_id', $request->user()->id)
            ->where('repository_id', $repositoryId)
            ->withCount('messages')
            ->latest()
            ->get()
            ->map(fn (Conversation $c) => [
                'id'             => $c->id,
                'title'          => $c->title,
                'messages_count' => $c->messages_count,
                'created_at'     => $c->created_at,
                'updated_at'     => $c->updated_at,
            ]);

        return response()->json(['data' => $conversations]);
    }

    /**
     * Delete a conversation owned by the authenticated user.
     *
     * DELETE /api/chat/conversations/{id}
     */
    public function deleteConversation(Request $request, int $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);

        if ($conversation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted.']);
    }

    /**
     * Find an existing conversation or create a new one, generating a title when new.
     */
    private function findOrCreateConversation(Repository $repository, ?int $conversationId, string $query): Conversation
    {
        if ($conversationId !== null) {
            $conversation = Conversation::find($conversationId);

            if ($conversation && $conversation->user_id === request()->user()->id) {
                return $conversation;
            }
        }

        $title = $this->llmService->generateTitle($query);

        return Conversation::create([
            'user_id'       => request()->user()->id,
            'repository_id' => $repository->id,
            'title'         => $title,
        ]);
    }

    /**
     * Persist a single message to the database.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    private function saveMessage(int $conversationId, string $role, string $content, ?array $metadata = null): Message
    {
        return Message::create([
            'conversation_id' => $conversationId,
            'role'            => $role,
            'content'         => $content,
            'metadata'        => $metadata,
        ]);
    }
}
