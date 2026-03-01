<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $messages = $conversation->messages()->get();
        return response()->json($messages);
    }
}
