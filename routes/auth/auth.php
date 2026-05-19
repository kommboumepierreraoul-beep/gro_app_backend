<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\api\AuthController;


//Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/register/admin', [AuthController::class, 'registerAdmin']);

    //login and logout routes
    Route::post('/login', [AuthController::class, 'Login']);

    Route::post('/logout', [AuthController::class, 'Logout'])->middleware('auth:sanctum');
});