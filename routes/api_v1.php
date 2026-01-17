<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\ClientController;
use App\Http\Controllers\API\V1\InverterController;
use App\Http\Controllers\API\V1\PlantInfoController;
use App\Http\Controllers\API\V1\InverterFaultController;

Route::post('client/login', [ClientController::class, 'clientLogin']);
Route::middleware('auth:client_api')->group(function () {
    Route::controller(ClientController::class)->group(function () {
        Route::post('client/logout', 'logout');
        Route::post('frontend/grouped-clients', 'frontendGroupedClients');
    });

    // Route::get('client/grouped-clients', 'groupedClients');
    Route::get('client/profile', function () {
        return auth()->guard('client_api')->user();
    });
});
// Public routes
Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('login', 'login');
    Route::post('company/register', 'companyRegister');
    Route::post('individual', 'companyIndividual');
    Route::post('company/generate/code', 'generateCode');
});

Route::controller(InverterController::class)->group(function () {
    Route::post('/inverters/{id}/command', 'sendCommand');
    Route::get('/inverter', 'index');
    Route::get('/inverter/data', 'inverter_data');
    Route::get('/inverter/latest_data', 'inverter_data_details');
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

    Route::controller(PlantInfoController::class)->group(function () {
        Route::get('plants/statistics-by-day', 'byDay');
        Route::get('plants/statistics-by-month', 'byMonth');
        Route::get('plants/statistics-by-year', 'byYear');
        Route::get('plants/statistics-by-total', 'byTotal');
        Route::get('plants/{id}', 'index');
        Route::get('plants/show/{id}', 'show');
    });

    Route::controller(InverterFaultController::class)->group(function () {
        Route::get('faults/', 'index');
    });

    Route::get('/run-inverter-command', function () {
        \Artisan::call('getInverterStatus:cron');
        return response()->json([
                'status' => true,
                'message' => 'Inverter Status Command Executed Successfully'
            ]);
    });
});
