<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RepositoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    Route::apiResource('repositories', RepositoryController::class)->only([
        'index', 'store', 'show', 'destroy',
    ]);
    Route::get('/repositories/{repository}/status', [RepositoryController::class, 'status']);
    Route::post('/repositories/{repository}/reindex', [RepositoryController::class, 'reindex']);

    Route::post('/chat', [ChatController::class, 'query']);
    Route::post('/chat/stream', [ChatController::class, 'stream']);
    Route::post('/chat/analyze-error', [ChatController::class, 'analyzeError']);
    Route::get('/chat/{repositoryId}/conversations', [ChatController::class, 'conversations']);
    Route::delete('/chat/conversations/{id}', [ChatController::class, 'deleteConversation']);
});
