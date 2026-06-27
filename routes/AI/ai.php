<?php

use App\Http\Controllers\Api\AI\AiChatController;
use App\Http\Controllers\Api\AI\AiSuggestionController;
use App\Http\Controllers\Api\AI\ModerationController;
use App\Http\Controllers\Api\AI\ConversationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Routes
|--------------------------------------------------------------------------
*/

Route::prefix('ai')->middleware(['auth:sanctum'])->group(function () {

    // ── Chat ──────────────────────────────────────────────────────────────────
    Route::post('/chat', AiChatController::class);

    // ── Suggestions ───────────────────────────────────────────────────────────
    Route::prefix('/suggestions')->group(function () {
        Route::post('/tags', [AiSuggestionController::class, 'tags']);
        Route::post('/summarize', [AiSuggestionController::class, 'summarize']);
        Route::post('/improve', [AiSuggestionController::class, 'improve']);
    });

    // ── Modération (admin only) ──────────────────────────────────────────────
    Route::prefix('/moderation')->middleware('can:moderate_content')->group(function () {
        Route::post('/check', [ModerationController::class, 'check']);
        Route::get('/logs', [ModerationController::class, 'logs']);
        Route::get('/logs/{moderationLog}', [ModerationController::class, 'show']);
        Route::post('/recheck', [ModerationController::class, 'recheck']);
        Route::post('/batch', [ModerationController::class, 'batch']);
    });

    // ── Conversations ─────────────────────────────────────────────────────────
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::patch('/conversations/{id}', [ConversationController::class, 'update']);
    Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);
});
