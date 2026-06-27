<?php

use App\Http\Controllers\Api\Marketplace\CategoryController;
use App\Http\Controllers\Api\Marketplace\ShopController;
use App\Http\Controllers\Api\Marketplace\ProductController;
use App\Http\Controllers\Api\Marketplace\OrderController;
use App\Http\Controllers\Api\Marketplace\WishlistController;
use App\Http\Controllers\Api\Marketplace\ReviewController;
use Illuminate\Support\Facades\Route;

// =============================================================================
// PUBLIC ROUTES — pas besoin d'être connecté
// =============================================================================
Route::prefix('marketplace')->group(function () {

    // Catégories
    Route::get('/categories',      [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);

    // Boutiques
    Route::get('/shops',           [ShopController::class, 'index']);
    Route::get('/shops/{id}',      [ShopController::class, 'show']);

    // Produits
    Route::get('/products',           [ProductController::class, 'index']);
    Route::get('/products/featured',  [ProductController::class, 'featured']);
    Route::get('/products/{id}',      [ProductController::class, 'show']);
});

// =============================================================================
// AUTHENTICATED ROUTES — connecté obligatoire
// =============================================================================
Route::prefix('marketplace')->middleware(['auth:sanctum'])->group(function () {

    // ── Catégories (admin) ───────────────────────────────────────────────────
    Route::post('/categories',        [CategoryController::class, 'store']);
    Route::put('/categories/{id}',    [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // ── Boutique ─────────────────────────────────────────────────────────────
    Route::get('/shops/me',          [ShopController::class, 'myShop']);
    Route::post('/shops',            [ShopController::class, 'store']);
    Route::put('/shops/{id}',        [ShopController::class, 'update']);

    // ── Produits ─────────────────────────────────────────────────────────────
    Route::post('/products',          [ProductController::class, 'store']);
    Route::put('/products/{id}',      [ProductController::class, 'update']);
    Route::delete('/products/{id}',   [ProductController::class, 'destroy']);

    // ── Commandes ────────────────────────────────────────────────────────────
    Route::get('/orders',             [OrderController::class, 'index']);
    Route::post('/orders',            [OrderController::class, 'store']);
    Route::get('/orders/{id}',        [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel',[OrderController::class, 'cancel']);

    // ── Wishlist ─────────────────────────────────────────────────────────────
    Route::get('/wishlist',           [WishlistController::class, 'index']);
    Route::post('/wishlist/toggle',   [WishlistController::class, 'toggle']);

    // ── Avis ─────────────────────────────────────────────────────────────────
    Route::post('/products/{productId}/reviews', [ReviewController::class, 'store']);
    Route::delete('/reviews/{id}',               [ReviewController::class, 'destroy']);
});
