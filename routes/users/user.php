<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;




//User routes
Route::prefix('users', function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/{id}', [UserController::class, 'show']);
    Route::put('/{id}', [UserController::class, 'update']);
    Route::delete('/{id}', [UserController::class, 'destroy']);

    Route::post('/profile', [UserController::class, 'updateProfile'])->middleware('auth:sanctum');
});
