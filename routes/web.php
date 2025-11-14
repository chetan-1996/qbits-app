<?php

use App\Services\MqttService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/mqtt-test', function (MqttService $mqtt) {
    $mqtt->publish('test/topic', 'Hello from Laravel!');
    return 'Message published to MQTT!';
});
