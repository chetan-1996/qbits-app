<?php

use App\Services\MqttService;
use App\Http\Controllers\API\V1\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mqtt-test', function (MqttService $mqtt) {
    $mqtt->publish('test/topic', 'Hello from Laravel!');
    return 'Message published to MQTT!';
});

// Telemetry Dashboard Routes
Route::controller(TelemetryController::class)->prefix('telemetry')->group(function () {
    Route::get('history', 'index')->name('telemetry.history');
    Route::get('heartbeat', 'heartbeatView')->name('telemetry.heartbeat');
});
