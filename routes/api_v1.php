<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\ClientController;
use App\Http\Controllers\API\V1\InverterController;

// Public routes
Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('company/register', 'companyRegister');
    Route::post('company/generate/code', 'generateCode');
});

Route::controller(InverterController::class)->group(function () {
    Route::post('/inverters/{id}/command', 'sendCommand');
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::get('profile', 'profile');
        Route::post('logout', 'logout');
    });

    Route::controller(ClientController::class)->group(function () {
        Route::get('client/index', 'index');
        Route::get('dealer/index', 'companyUser');
        Route::post('client/whatsapp-notification-update', 'postWhatsAppNotificationUpdate');
        Route::post('client/set-company-code', 'setCompanyCodeToIndivisualUser');
        Route::get('client/inverter/totals', 'totals');
        Route::get('client/grouped-clients', 'groupedClients');
    });

    Route::get('/run-inverter-command', function () {
        \Artisan::call('getInverterStatus:cron');
        return response()->json([
                'status' => true,
                'message' => 'Inverter Status Command Executed Successfully'
            ]);
    });
});
