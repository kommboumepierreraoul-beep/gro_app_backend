<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::middleware(['auth:sanctum', 'verified.email'])->prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index']); // Get all users
    Route::get('/{id}', [UserController::class, 'show']); // Get user by ID
    Route::post('/', [UserController::class, 'store']); // Create new user
    Route::put('/{id}', [UserController::class, 'update']); // Update user by ID
    Route::delete('/{id}', [UserController::class, 'destroy']); // Delete user by ID
    Route::post('/restore/{id}', [UserController::class, 'restore']); // Restore deleted user by ID

});
