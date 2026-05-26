<?php

use App\Http\Controllers\Api\Community\PostController;
use App\Http\Controllers\Api\Community\CommentController;
use App\Http\Controllers\Api\Community\MessageController;
use App\Http\Controllers\Api\Community\FollowController;
use App\Http\Controllers\Api\Community\NotificationController;
use App\Http\Controllers\Api\Community\AnnouncementController;
use App\Http\Controllers\Api\Community\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('community')->middleware(['auth:sanctum'])->group(function () {

    // ── Profils ───────────────────────────────────────────────────────────────
    Route::prefix('profile')->group(function () {
        Route::get('/me',              [ProfileController::class, 'me']);
        Route::put('/me',              [ProfileController::class, 'update']);
        Route::get('/search',          [ProfileController::class, 'search']);
        Route::get('/{userId}',        [ProfileController::class, 'show']);
    });

    // ── Posts ─────────────────────────────────────────────────────────────────
    Route::prefix('posts')->group(function () {
        Route::get('/',                [PostController::class, 'index']);
        Route::post('/',               [PostController::class, 'store']);

        // ✅ Routes spécifiques EN PREMIER
        Route::get('/user/{userId}',   [PostController::class, 'userPosts']);

        // ✅ Wildcards EN DERNIER
        Route::get('/{id}',            [PostController::class, 'show']);
        Route::put('/{id}',            [PostController::class, 'update']);
        Route::delete('/{id}',         [PostController::class, 'destroy']);
        Route::post('/{id}/like',      [PostController::class, 'toggleLike']);
    });

    // ── Commentaires ──────────────────────────────────────────────────────────
    Route::prefix('posts/{postId}/comments')->group(function () {
        Route::get('/',                [CommentController::class, 'index']);
        Route::post('/',               [CommentController::class, 'store']);
    });
    Route::prefix('comments')->group(function () {
        Route::put('/{id}',            [CommentController::class, 'update']);
        Route::delete('/{id}',         [CommentController::class, 'destroy']);
        Route::post('/{id}/like',      [CommentController::class, 'toggleLike']);
    });

    // ── Follow ────────────────────────────────────────────────────────────────
    Route::prefix('users')->group(function () {
        // ✅ Route statique EN PREMIER
        Route::get('/suggestions',           [FollowController::class, 'suggestions']);

        // ✅ Wildcards EN DERNIER
        Route::post('/{userId}/follow',      [FollowController::class, 'toggle']);
        Route::get('/{userId}/followers',    [FollowController::class, 'followers']);
        Route::get('/{userId}/following',    [FollowController::class, 'following']);
    });

    // ── Messages ──────────────────────────────────────────────────────────────
    Route::prefix('messages')->group(function () {
        // ✅ Routes statiques EN PREMIER
        Route::get('/conversations',                       [MessageController::class, 'conversations']);
        Route::post('/conversations',                      [MessageController::class, 'createOrFind']);

        // ✅ Wildcards EN DERNIER
        Route::get('/conversations/{id}/messages',         [MessageController::class, 'messages']);
        Route::post('/conversations/{id}/messages',        [MessageController::class, 'send']);
        Route::delete('/messages/{id}',                    [MessageController::class, 'deleteMessage']);
    });

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::prefix('notifications')->group(function () {
        // ✅ Route statique EN PREMIER
        Route::get('/',                [NotificationController::class, 'index']);
        Route::put('/read-all',        [NotificationController::class, 'markAllRead']);

        // ✅ Wildcards EN DERNIER
        Route::put('/{id}/read',       [NotificationController::class, 'markRead']);
        Route::delete('/{id}',         [NotificationController::class, 'destroy']);
    });

    // ── Annonces ──────────────────────────────────────────────────────────────
    Route::prefix('announcements')->group(function () {
        Route::get('/',                [AnnouncementController::class, 'index']);
        Route::post('/',               [AnnouncementController::class, 'store']);

        // ✅ Wildcards EN DERNIER
        Route::get('/{id}',            [AnnouncementController::class, 'show']);
        Route::delete('/{id}',         [AnnouncementController::class, 'destroy']);
        Route::post('/{id}/like',      [AnnouncementController::class, 'toggleLike']);
    });
});
