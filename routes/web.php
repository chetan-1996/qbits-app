<?php

use App\Services\MqttService;
use App\Http\Controllers\API\V1\TelemetryController;
use App\Http\Controllers\API\V1\ChannelPartnerController;
use App\Http\Controllers\API\V1\DongleController;
use App\Http\Controllers\FirmwareController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/channel-partners/import', function () {
    return view('channel-partners.import');
});

Route::post('/channel-partners/import', [ChannelPartnerController::class, 'import']);

Route::get('/dongles/import', function () {
    return view('dongles.import');
});

Route::post('/dongles/import', [DongleController::class, 'import'])->name('dongles.import');

Route::get('/mqtt-test', function (MqttService $mqtt) {
    $mqtt->publish('test/topic', 'Hello from Laravel!');
    return 'Message published to MQTT!';
});

// Telemetry Dashboard Routes
Route::controller(TelemetryController::class)->prefix('telemetry')->group(function () {
    Route::get('history', 'index')->name('telemetry.history');
    Route::get('history-filtered', 'filteredIndex')->name('telemetry.history.filtered');
    Route::get('history-filtered/export', 'exportFiltered')->name('telemetry.history.filtered.export');
    Route::get('heartbeat', 'heartbeatView')->name('telemetry.heartbeat');
    Route::get('modbus-write', 'modbusWriteView')->name('telemetry.modbus-write');
    Route::get('chart', 'chart')->name('telemetry.chart');
    Route::post('chart/data', 'chartData')->name('telemetry.chart.data');
});

// Firmware Upload (Admin Only)
Route::middleware(['web', 'admin.auth'])->prefix('admin')->group(function () {
    Route::get('firmware', [FirmwareController::class, 'index'])->name('firmware.index');
    Route::post('firmware', [FirmwareController::class, 'store'])->name('firmware.store');
    Route::delete('firmware', [FirmwareController::class, 'destroy'])->name('firmware.destroy');
});

// Public Firmware Download (serves static .bin files directly)
Route::get('firmware/download/{file}', function ($file) {
    $allowedDir = realpath(storage_path('app/public/static/firmware'));
    $path = realpath($allowedDir . '/' . basename($file));

    if ($path === false || !str_starts_with($path, $allowedDir) || !is_file($path)) {
        abort(404, 'Firmware file not found.');
    }

    return response()->file($path, [
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => 'attachment; filename="' . basename($file) . '"',
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->where('file', '[\w\-\.]+\.bin$')->name('firmware.download');
