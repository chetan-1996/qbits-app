<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\ClientController;

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

    Route::controller(ClientController::class)->group(function () {
        Route::get('client/index', 'index');
    });
});
