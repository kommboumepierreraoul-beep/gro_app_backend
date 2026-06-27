<?php
use App\Http\Controllers\api\AI\AiChatController;
use App\Http\Controllers\api\AI\AiSuggestionController;
use App\Http\Controllers\api\AI\ModerationController;
use App\Http\Controllers\api\AI\ConversationController;
use Illuminate\Support\Facades\Route;

Route::prefix('ai')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/chat', [AiChatController::class, '__invoke']);
    Route::prefix('/suggestions')->group(function () {
        Route::post('/tags', [AiSuggestionController::class, 'tags']);
        Route::post('/summarize', [AiSuggestionController::class, 'summarize']);
        Route::post('/improve', [AiSuggestionController::class, 'improve']);
    });
    Route::prefix('/moderation')->middleware('can:moderate_content')->group(function () {
        Route::post('/check', [ModerationController::class, 'check']);
        Route::get('/logs', [ModerationController::class, 'logs']);
        Route::get('/logs/{moderationLog}', [ModerationController::class, 'show']);
        Route::post('/recheck', [ModerationController::class, 'recheck']);
        Route::post('/batch', [ModerationController::class, 'batch']);
    });
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::patch('/conversations/{id}', [ConversationController::class, 'update']);
    Route::delete('/conversations/{id}', [ConversationController::class, 'destroy']);
});
