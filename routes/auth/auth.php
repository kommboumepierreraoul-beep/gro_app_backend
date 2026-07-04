<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes - Authentification
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    // =====================================================================
    // ROUTES PUBLIQUES (Accessibles sans authentification)
    // =====================================================================

    // Inscription
    Route::post('/register', [AuthController::class, 'registerUser']);
    Route::post('/register/admin', [AuthController::class, 'registerAdmin']);

    // Connexion
    Route::post('/login', [AuthController::class, 'login']);

    // Mot de passe oublié
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Google OAuth
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);

    // =====================================================================
    // ROUTES PROTÉGÉES (Nécessitent un token d'authentification)
    // =====================================================================

    Route::middleware('auth:sanctum')->group(function () {

        // Déconnexion
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout/all', [AuthController::class, 'logoutAll']);

        // Rafraîchissement du token
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

        // Vérification email
        Route::post('/send-verification', [AuthController::class, 'sendVerificationCode']);
        Route::post('/verify-email', [AuthController::class, 'verifyEmail']);

        // Profil utilisateur
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);

        // Changement de mot de passe
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        // Récupération des données OAuth
        Route::get('/oauth-user', [AuthController::class, 'getOAuthUser']);
    });
});

// =====================================================================
// ROUTES SUPPLÉMENTAIRES (Hors du préfixe 'auth')
// =====================================================================

// Si vous avez besoin d'autres routes liées à l'authentification
// comme la vérification d'email par lien (si vous utilisez cette méthode)
Route::get('/email/verify/{id}/{hash}', function ($id, $hash) {
    // Logique de vérification par email
})->middleware(['auth:sanctum', 'signed'])->name('verification.verify');

Route::post('/email/resend', function (Request $request) {
    // Renvoyer le lien de vérification
})->middleware(['auth:sanctum', 'throttle:6,1'])->name('verification.send');
