<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RepositoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/user', fn (Request $request) => $request->user());

    Route::apiResource('repositories', RepositoryController::class)->only([
        'index', 'show', 'destroy',
    ]);
    Route::post('/repositories', [RepositoryController::class, 'store'])
        ->middleware('throttle:repository-create');
    Route::get('/repositories/{repository}/status', [RepositoryController::class, 'status']);
    Route::get('/repositories/{repository}/files', [RepositoryController::class, 'files']);
    Route::post('/repositories/{repository}/reindex', [RepositoryController::class, 'reindex'])
        ->middleware('throttle:repository-create');

    Route::post('/chat', [ChatController::class, 'query'])->middleware('throttle:chat');
    Route::post('/chat/stream', [ChatController::class, 'stream'])->middleware('throttle:chat');
    Route::post('/chat/analyze-error', [ChatController::class, 'analyzeError'])->middleware('throttle:chat');
    Route::get('/chat/{repositoryId}/conversations', [ChatController::class, 'conversations']);
    Route::delete('/chat/conversations/{id}', [ChatController::class, 'deleteConversation']);
    Route::get('/chat/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
});
