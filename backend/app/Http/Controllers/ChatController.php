<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function query(Request $request): JsonResponse
    {
        // TODO: Implement RAG query in MVP
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    public function stream(Request $request): void
    {
        // TODO: Implement SSE streaming in MVP
        abort(501, 'Not yet implemented');
    }

    public function analyzeError(Request $request): JsonResponse
    {
        // TODO: Implement error log analysis in MVP
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    public function conversations(Request $request, int $repositoryId): JsonResponse
    {
        // TODO: Implement conversation listing in MVP
        return response()->json(['data' => []]);
    }
}
