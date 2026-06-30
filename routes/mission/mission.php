<?php

// routes/api.php — Ajouter ces routes dans votre fichier api.php existant
// ⚠️ Toutes les routes missions nécessitent auth:sanctum

use App\Http\Controllers\api\mission\MissionController;
use App\Http\Controllers\api\mission\MissionApplicationController;
use App\Http\Controllers\api\mission\MissionReviewController;
use App\Http\Controllers\api\mission\MissionReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // ── Missions ──────────────────────────────────────────────────────────

    Route::prefix('missions')->name('missions.')->group(function () {

        // ⚠️ IMPORTANT : routes nommées AVANT {ulid} pour éviter les conflits
        Route::get('/categories', [MissionController::class, 'categories'])->name('categories');
        Route::get('/map',        [MissionController::class, 'mapPoints'])->name('map');
        Route::get('/my',         [MissionController::class, 'my'])->name('my');

        // CRUD principal
        Route::get('/',           [MissionController::class, 'index'])->name('index');
        Route::post('/',          [MissionController::class, 'store'])->name('store');
        Route::get('/{ulid}',     [MissionController::class, 'show'])->name('show');
        Route::put('/{ulid}',     [MissionController::class, 'update'])->name('update');
        Route::delete('/{ulid}',  [MissionController::class, 'destroy'])->name('destroy');

        // Actions
        Route::patch('/{ulid}/status', [MissionController::class, 'updateStatus'])->name('update-status');
        Route::post('/{ulid}/view',    [MissionController::class, 'recordView'])->name('record-view');

        // Candidatures (côté auteur)
        Route::prefix('/{ulid}/applications')->name('applications.')->group(function () {
            Route::get('/',                  [MissionApplicationController::class, 'index'])->name('index');
            Route::patch('/{appId}/accept',  [MissionApplicationController::class, 'accept'])->name('accept');
            Route::patch('/{appId}/reject',  [MissionApplicationController::class, 'reject'])->name('reject');
            Route::patch('/{appId}/note',    [MissionApplicationController::class, 'addNote'])->name('note');
        });

        // Évaluations
        Route::prefix('/{ulid}/reviews')->name('reviews.')->group(function () {
            Route::get('/',   [MissionReviewController::class, 'index'])->name('index');
            Route::post('/',  [MissionReviewController::class, 'store'])->name('store');
        });

        // Signalements
        Route::prefix('/{ulid}/report')->name('report.')->group(function () {
            Route::get('/',  [MissionReportController::class, 'check'])->name('check');
            Route::post('/', [MissionReportController::class, 'store'])->name('store');
        });
    });

    // ── Candidatures (côté candidat) ─────────────────────────────────────

    Route::prefix('applications')->name('applications.')->group(function () {
        Route::post('/',         [MissionApplicationController::class, 'store'])->name('store');
        Route::get('/my',        [MissionApplicationController::class, 'my'])->name('my');
        Route::delete('/{id}',   [MissionApplicationController::class, 'withdraw'])->name('withdraw');
    });
});
