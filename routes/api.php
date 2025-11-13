<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\WebhookController;

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
    Route::post('/webhook/individual', [WebhookController::class, 'individualReceive']);
    Route::post('/webhook/company', [WebhookController::class, 'companyReceive']);
    Route::post('/webhook/whatsapp-notification/update', [WebhookController::class, 'postWhatsAppNotification']);
    Route::get('/webhook/whatsapp-notification/{userId}', [WebhookController::class, 'getWhatsAppNotification']);
   // Route::post('/webhook/company', [WebhookController::class, 'companyReceive']);
});

// Fallback for invalid URLs
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Invalid API endpoint.'
    ], 404);
});
