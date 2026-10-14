<?php

use App\Services\MqttService;
use App\Http\Controllers\API\V1\TelemetryController;
use App\Http\Controllers\API\V1\ChannelPartnerController;
use App\Http\Controllers\API\V1\DongleController;
use App\Http\Controllers\FirmwareController;
use App\Http\Controllers\OtaJobController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/channel-partners/import', function () {
    return view('channel-partners.import');
});

Route::post('/channel-partners/import', [ChannelPartnerController::class, 'import']);

Route::get('/channel-partners/map', function () {
    return view('channel-partners.map');
});

Route::get('/dongles', function () {
    $query = \App\Models\Dongle::query();

    if ($search = request('dongle_id')) {
        $query->where('dongle_id', 'like', '%' . $search . '%');
    }
    if ($search = request('imei')) {
        $query->where('imei', 'like', '%' . $search . '%');
    }
    if ($search = request('sim_num')) {
        $query->where('sim_num', 'like', '%' . $search . '%');
    }

    $totalCount = \App\Models\Dongle::count();
    $activeCount = \App\Models\Dongle::where('status', 1)->count();
    $inactiveCount = \App\Models\Dongle::where('status', '!=', 1)->orWhereNull('status')->count();

    $dongles = $query->orderBy('id', 'desc')->paginate(20)->appends(request()->query());

    return view('dongles.list', compact('dongles', 'totalCount', 'activeCount', 'inactiveCount'));
})->name('dongles.list');

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

// Mass OTA portal (operators + admins; force/backup are admin-only server-side)
Route::middleware(['ota.auth'])->prefix('ota')->name('ota.')->group(function () {
    Route::match(['GET', 'POST'], 'login', function () {
        // Reached only when already authenticated — the middleware otherwise
        // renders the login form or handles the password POST itself.
        return redirect()->route('ota.jobs.index');
    })->name('login');
    Route::get('jobs', [OtaJobController::class, 'index'])->name('jobs.index');
    Route::get('devices', [OtaJobController::class, 'devices'])->name('devices');
    Route::get('jobs/create', [OtaJobController::class, 'create'])->name('jobs.create');
    Route::get('template', [OtaJobController::class, 'template'])->name('template');
    Route::post('jobs/preview', [OtaJobController::class, 'preview'])->name('jobs.preview');
    Route::get('jobs/{job}', [OtaJobController::class, 'show'])->name('jobs.show');
    Route::get('jobs/{job}/rows', [OtaJobController::class, 'rows'])->name('jobs.rows');
    Route::post('jobs/{job}/send', [OtaJobController::class, 'send'])->name('jobs.send');
    Route::post('jobs/{job}/pause', [OtaJobController::class, 'pause'])->name('jobs.pause');
    Route::post('jobs/{job}/resume', [OtaJobController::class, 'resume'])->name('jobs.resume');
    Route::post('jobs/{job}/stop', [OtaJobController::class, 'stop'])->name('jobs.stop');
    Route::post('jobs/{job}/retry', [OtaJobController::class, 'retry'])->name('jobs.retry');
    Route::get('jobs/{job}/export', [OtaJobController::class, 'export'])->name('jobs.export');
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
