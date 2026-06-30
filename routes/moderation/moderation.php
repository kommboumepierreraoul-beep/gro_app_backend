<?php

use App\Http\Controllers\api\Moderation\ModerationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('moderation')->group(function () {

    // ── Statistiques ──────────────────────────────────────────────────────────
    Route::get('/stats', [ModerationController::class, 'stats']);

    // ── Files de review ──────────────────────────────────────────────────────
    Route::prefix('queue')->group(function () {
        Route::get('/posts', [ModerationController::class, 'reviewQueuePosts']);
    });

    // ── Actions de modération (admin uniquement) ─────────────────────────────
    Route::middleware(['role:admin'])->group(function () {
        // Posts
        Route::post('/posts/{post}/moderate', [ModerationController::class, 'moderatePost']);
        Route::post('/posts/{post}/reanalyze', [ModerationController::class, 'reanalyzePost']);
        Route::get('/posts/{post}/moderation', [ModerationController::class, 'getPostModeration']);

        // Audit
        Route::get('/audit', [ModerationController::class, 'auditLog']);
        Route::get('/audit/export', [ModerationController::class, 'exportAudit']);

        // Modération en masse
        Route::post('/bulk/approve', [ModerationController::class, 'bulkApprove']);
        Route::post('/bulk/reject', [ModerationController::class, 'bulkReject']);
    });

    // ── Routes utilisateur ─────────────────────────────────────────────────────
    Route::prefix('my')->group(function () {
        Route::get('/pending', [ModerationController::class, 'myPendingContent']);
        Route::get('/rejected', [ModerationController::class, 'myRejectedContent']);
        Route::get('/approved', [ModerationController::class, 'myApprovedContent']);
        Route::get('/review', [ModerationController::class, 'myReviewContent']);
        Route::get('/summary', [ModerationController::class, 'myModerationSummary']);
    });

    // ✅ AJOUTER CETTE ROUTE ────────────────────────────────────────────────────
    // ── Statut de publication ──────────────────────────────────────────────────
    Route::get('/publishing-status', [ModerationController::class, 'getPublishingStatus']);
});
