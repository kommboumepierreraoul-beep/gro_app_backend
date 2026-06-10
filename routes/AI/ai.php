<?php
// routes/api.php

use App\Http\Controllers\Api\AI\ChatController;
use App\Http\Controllers\Api\AI\ModerationController;
use App\Http\Controllers\Api\AI\SuggestionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Module IA
|--------------------------------------------------------------------------
|
| Toutes les routes IA sont protégées par :
|   1. auth:sanctum  → utilisateur authentifié requis
|   2. throttle:ai   → rate limiting custom (défini dans AppServiceProvider)
|
*/

Route::middleware(['auth:sanctum', 'throttle:ai'])
    ->prefix('ai')
    ->name('ai.')
    ->group(function () {

        // ── Chat ─────────────────────────────────────────────
        Route::prefix('chat')->name('chat.')->group(function () {
            Route::post('/',        [ChatController::class, 'sendMessage'])->name('send');
            Route::get('/stream',   [ChatController::class, 'stream'])->name('stream');
        });

        // ── Conversations ─────────────────────────────────────
        Route::prefix('conversations')->name('conversations.')->group(function () {
            Route::get('/',    [ChatController::class, 'listConversations'])->name('index');
            Route::post('/',   [ChatController::class, 'startConversation'])->name('store');
        });

        // ── Suggestions et outils de rédaction ───────────────
        Route::post('/tags',         [SuggestionController::class, 'generateTags'])->name('tags');
        Route::post('/summarize',    [SuggestionController::class, 'summarizeThread'])->name('summarize');
        Route::post('/improve-post', [SuggestionController::class, 'improvePost'])->name('improve-post');

        // ── Modération manuelle (admin + modérateur uniquement) ─
        Route::middleware('can:moderate-content')
            ->post('/moderate', [ModerationController::class, 'check'])
            ->name('moderate');
    });
