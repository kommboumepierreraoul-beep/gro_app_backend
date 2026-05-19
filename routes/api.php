<?php

use App\Http\Controllers\api\AuthController;
use Illuminate\Support\Facades\Route;

// =============================================================================
// PUBLIC ROUTES (no authentication required)
// =============================================================================

Route::prefix('auth')->group(function () {

    // Registration
    Route::post('/register',       [AuthController::class, 'registerUser']);
    Route::post('/register/admin', [AuthController::class, 'registerAdmin']);

    // Login
    Route::post('/login', [AuthController::class, 'login']);

    // Password reset (no auth needed — user is locked out)
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password',  [AuthController::class, 'resetPassword']);
});

// =============================================================================
// AUTHENTICATED ROUTES (Sanctum token required)
// =============================================================================

Route::middleware('auth:sanctum')->prefix('auth')->group(function () {

    // Email verification
    Route::post('/email/resend', [AuthController::class, 'sendVerificationCode']);
    Route::post('/email/verify', [AuthController::class, 'verifyEmail']);

    // Token management
    Route::post('/refresh', [AuthController::class, 'refreshToken']);
    Route::post('/logout',  [AuthController::class, 'logout']);
    Route::post('/logout/all', [AuthController::class, 'logoutAll']);
});

// =============================================================================
// AUTHENTICATED + VERIFIED ROUTES
// =============================================================================

Route::middleware(['auth:sanctum', 'verified.email'])->prefix('auth')->group(function () {

    // Profile
    Route::get('/profile',  [AuthController::class, 'profile']);
    Route::put('/profile',  [AuthController::class, 'updateProfile']);

    // Password change (requires old password)
    Route::put('/password', [AuthController::class, 'changePassword']);
});

// =============================================================================
// ADMIN ONLY (example — add your admin routes here)
// =============================================================================

// Route::middleware(['auth:sanctum', 'verified.email', 'role:admin'])->prefix('admin')->group(function () {
//     // ...
// });