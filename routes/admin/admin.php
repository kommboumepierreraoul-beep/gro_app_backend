<?php

use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminActivityController;
use App\Http\Controllers\Api\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

// ========== ADMIN ROUTES - Authentifié obligatoire ==========
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // Produits en attente
    Route::get('products/pending', [AdminProductController::class, 'pendingProducts']);
    Route::post('products/{id}/approve', [AdminProductController::class, 'approveProduct']);
    Route::post('products/{id}/reject', [AdminProductController::class, 'rejectProduct']);
    
    // Tous les produits (catalogue)
    Route::get('products', [AdminProductController::class, 'allProducts']);
    Route::delete('products/{id}', [AdminProductController::class, 'deleteProduct']);
    
    // Utilisateurs
    Route::get('users', [AdminUserController::class, 'allUsers']);
    Route::post('users/{id}/suspend', [AdminUserController::class, 'suspendUser']);
    Route::post('users/{id}/unsuspend', [AdminUserController::class, 'unsuspendUser']);
    Route::delete('users/{id}', [AdminUserController::class, 'deleteUser']);
    
    // Activités
    Route::get('activities', [AdminActivityController::class, 'getActivityLog']);
    
    // Analytiques
    Route::get('analytics', [AdminAnalyticsController::class, 'getAnalytics']);
    
    // Catégories
    Route::apiResource('categories', AdminCategoryController::class)->names('admin.categories');

    // Commandes
    Route::get('orders', [OrderController::class, 'adminOrders']);
});
