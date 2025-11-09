<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\WebhookController;

$apiVersion = config('app.api_version');

Route::prefix($apiVersion)->group(function () use ($apiVersion) {
    $routeFile = base_path("routes/api_{$apiVersion}.php");

    if (file_exists($routeFile)) {
        require $routeFile;
    } else {
        Route::fallback(function () use ($apiVersion) {
            return response()->json([
                'success' => false,
                'message' => "API version '{$apiVersion}' not found."
            ], 404);
        });
    }
});

Route::prefix('v1')->group(function () {
    Route::post('/webhook/receive', [WebhookController::class, 'receive']);
});

// Fallback for invalid URLs
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Invalid API endpoint.'
    ], 404);
});
