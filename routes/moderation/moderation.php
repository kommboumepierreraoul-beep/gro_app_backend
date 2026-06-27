<?php

use App\Http\Controllers\API\Moderation\ModerationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes de Modération
|--------------------------------------------------------------------------
*/

Route::prefix('moderation')->middleware(['auth:sanctum'])->group(function () {

    // ── Statistiques ──────────────────────────────────────────────────────────
    Route::get('/stats', [ModerationController::class, 'stats']);

    // ── Providers IA ──────────────────────────────────────────────────────────
    Route::get('/providers', [ModerationController::class, 'providers']);
    Route::post('/switch-provider', [ModerationController::class, 'switchProvider']);

    // ── Test de modération ────────────────────────────────────────────────────
    Route::post('/test', [ModerationController::class, 'test']);

    // ── Files de review ──────────────────────────────────────────────────────
    Route::prefix('queue')->group(function () {
        Route::get('/posts', [ModerationController::class, 'reviewQueuePosts']);
        Route::get('/comments', [ModerationController::class, 'reviewQueueComments']);
        Route::get('/messages', [ModerationController::class, 'reviewQueueMessages']);
    });

    // ── Actions de modération (admin uniquement) ─────────────────────────────
    Route::middleware(['role:admin'])->group(function () {

        // Posts
        Route::post('/posts/{post}/moderate', [ModerationController::class, 'moderatePost']);
        Route::post('/posts/{post}/reanalyze', [ModerationController::class, 'reanalyzePost']);
        Route::get('/posts/{post}/moderation', [ModerationController::class, 'getPostModeration']);
        Route::delete('/posts/{post}/moderation', [ModerationController::class, 'deletePostModeration']);

        // Comments
        Route::post('/comments/{comment}/moderate', [ModerationController::class, 'moderateComment']);
        Route::post('/comments/{comment}/reanalyze', [ModerationController::class, 'reanalyzeComment']);
        Route::get('/comments/{comment}/moderation', [ModerationController::class, 'getCommentModeration']);
        Route::delete('/comments/{comment}/moderation', [ModerationController::class, 'deleteCommentModeration']);

        // Messages
        Route::post('/messages/{message}/moderate', [ModerationController::class, 'moderateMessage']);
        Route::post('/messages/{message}/reanalyze', [ModerationController::class, 'reanalyzeMessage']);
        Route::get('/messages/{message}/moderation', [ModerationController::class, 'getMessageModeration']);
        Route::delete('/messages/{message}/moderation', [ModerationController::class, 'deleteMessageModeration']);

        // Audit
        Route::get('/audit', [ModerationController::class, 'auditLog']);
        Route::get('/audit/export', [ModerationController::class, 'exportAudit']);
        Route::delete('/audit/cleanup', [ModerationController::class, 'cleanupAudit']);

        // Modération en masse
        Route::post('/bulk/approve', [ModerationController::class, 'bulkApprove']);
        Route::post('/bulk/reject', [ModerationController::class, 'bulkReject']);
        Route::post('/bulk/review', [ModerationController::class, 'bulkReview']);

        // Statistiques avancées
        Route::get('/stats/daily', [ModerationController::class, 'dailyStats']);
        Route::get('/stats/weekly', [ModerationController::class, 'weeklyStats']);
        Route::get('/stats/monthly', [ModerationController::class, 'monthlyStats']);
        Route::get('/stats/export', [ModerationController::class, 'exportStats']);
    });

    // ── Routes utilisateur (propriétaire du contenu) ─────────────────────────
    Route::prefix('my')->group(function () {
        Route::get('/pending', [ModerationController::class, 'myPendingContent']);
        Route::get('/rejected', [ModerationController::class, 'myRejectedContent']);
        Route::get('/approved', [ModerationController::class, 'myApprovedContent']);
        Route::get('/review', [ModerationController::class, 'myReviewContent']);
        Route::get('/summary', [ModerationController::class, 'myModerationSummary']);
    });

    // ── Signalements ──────────────────────────────────────────────────────────
    Route::prefix('reports')->group(function () {
        // Créer un signalement
        Route::post('/', [ModerationController::class, 'createReport']);

        // Mes signalements
        Route::get('/my', [ModerationController::class, 'myReports']);

        // Admin uniquement
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/', [ModerationController::class, 'allReports']);
            Route::get('/pending', [ModerationController::class, 'pendingReports']);
            Route::post('/{report}/resolve', [ModerationController::class, 'resolveReport']);
            Route::post('/{report}/dismiss', [ModerationController::class, 'dismissReport']);
            Route::get('/stats', [ModerationController::class, 'reportStats']);
        });
    });

    // ── Configuration de modération (admin uniquement) ──────────────────────
    Route::prefix('config')->middleware(['role:admin'])->group(function () {
        Route::get('/thresholds', [ModerationController::class, 'getThresholds']);
        Route::put('/thresholds', [ModerationController::class, 'updateThresholds']);
        Route::get('/blocklist', [ModerationController::class, 'getBlocklist']);
        Route::post('/blocklist', [ModerationController::class, 'addToBlocklist']);
        Route::delete('/blocklist/{word}', [ModerationController::class, 'removeFromBlocklist']);
        Route::get('/spam-domains', [ModerationController::class, 'getSpamDomains']);
        Route::post('/spam-domains', [ModerationController::class, 'addSpamDomain']);
        Route::delete('/spam-domains/{domain}', [ModerationController::class, 'removeSpamDomain']);
    });
});
