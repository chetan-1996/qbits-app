<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API Version 1
Route::prefix(config('app.api_version'))->group(function () {
    // Public routes
    Route::controller(AuthController::class)->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login');
    });

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        Route::controller(AuthController::class)->group(function () {
            Route::get('profile', 'profile');
            Route::post('logout', 'logout');
        });

    });
});
