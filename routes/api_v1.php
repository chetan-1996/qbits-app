<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\ClientController;
use App\Http\Controllers\API\V1\InverterController;
use App\Http\Controllers\API\V1\PlantInfoController;
use App\Http\Controllers\API\V1\InverterFaultController;
use App\Http\Controllers\API\V1\InverterCommandController;
use App\Http\Controllers\API\V1\DashboardController;
// use App\Http\Controllers\API\V1\LeadCategoryController;
// use App\Http\Controllers\API\V1\LeadSubcategoryController;
use App\Http\Controllers\API\V1\StateController;
use App\Http\Controllers\API\V1\ChannelPartnerController;

// Route::get('/lead-categories', [LeadCategoryController::class,'index']);
// Route::post('/lead-categories', [LeadCategoryController::class, 'store']);
// Route::put('/lead-categories/{id}', [LeadCategoryController::class, 'update']);
// Route::delete('/lead-categories/{id}', [LeadCategoryController::class, 'destroy']);
// Route::get('/lead-categories/{id}', [LeadCategoryController::class, 'show']);

// Route::apiResource(
//   'lead-subcategories',
//   LeadSubcategoryController::class
// );


Route::post('client/login', [ClientController::class, 'clientLogin']);
Route::get('/states', [StateController::class, 'index']);
Route::prefix('channel-partners')->controller(ChannelPartnerController::class)->group(function () {
    Route::get('/', 'index');
    Route::get('/{id}','show');
    Route::post('/','store');
    Route::post('/{id}','update');
    Route::delete('/{id}','destroy');
});
Route::middleware('auth:client_api')->group(function () {
    Route::controller(DashboardController::class)->group(function () {
        Route::get('frontend/dashboard/widget-total', 'frontendWidgetTotals');
    });
    Route::controller(ClientController::class)->group(function () {
        Route::post('client/logout', 'logout');
        Route::get('frontend/grouped-clients', 'frontendGroupedClients');
        Route::get('frontend/inverter/totals', 'frontendTotals');
    });

    Route::controller(PlantInfoController::class)->group(function () {
        Route::get('frontend/plants/statistics-by-day', 'frontendByDay');
        Route::get('frontend/plants/statistics-by-month', 'frontendByMonth');
        Route::get('frontend/plants/statistics-by-year', 'frontendByYear');
        Route::get('frontend/plants/statistics-by-total', 'frontendByTotal');
        Route::get('frontend/plants/{id}', 'frontendIndex');
        Route::get('frontend/plants/show/{id}', 'frontendShow');
        Route::post('frontend/create-plant', 'createPlant');
        Route::post('frontend/add-collector', 'addCollector');
    });

    Route::controller(InverterController::class)->group(function () {
        Route::get('/frontend/inverter', 'frontendIndex');
        Route::get('/frontend/inverter/data', 'frontend_inverter_data');
        Route::get('/frontend/inverter/latest_data', 'frontend_inverter_data_details');
        Route::get('/frontend/inverter/all_latest_data', 'frontend_inverter_data_details_list');

    });

    Route::controller(InverterFaultController::class)->group(function () {
        Route::get('frontend/faults/', 'frontendIndex');
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

Route::controller(InverterCommandController::class)->group(function () {
    Route::post('/inverter/command/cmd', 'sendCmd');
    Route::post('/inverter/command/ota', 'sendOta');
    // Route::post('/inverters/{id}/command', 'sendCommand');
});

Route::controller(InverterController::class)->group(function () {
    Route::post('/inverter/command', 'sendCommand');
    // Route::post('/inverters/{id}/command', 'sendCommand');
    Route::get('/inverter', 'index');
    Route::get('/inverter/data', 'inverter_data');
    Route::get('/inverter/latest_data', 'inverter_data_details');
    Route::get('/inverter/all_latest_data', 'inverter_data_details_list');
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {

    Route::controller(DashboardController::class)->group(function () {
        Route::get('dashboard/widget-total', 'widgetTotals');
    });

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
