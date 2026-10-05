<?php

/**
 * Virtual AL0228 dongle for local OTA testing.
 * Usage: php ota_test_dongle.php <IMEI> [from_ver]
 * Subscribes to <prefix>/<IMEI>/ota/sub and answers on .../ota/pub with
 * ACCEPTED -> DOWNLOADING -> SUCCESS (to_ver = requested ver).
 */
require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$imei = $argv[1] ?? '865056070026808';
$fromVer = $argv[2] ?? '2.0.8';
$prefix = config('ota.topic_prefix');
$sub = "{$prefix}/{$imei}/ota/sub";
$pub = "{$prefix}/{$imei}/ota/pub";

$mqtt = new PhpMqtt\Client\MqttClient(
    config('mqtt.host'),
    config('mqtt.port'),
    'virtual-dongle-' . $imei
);
$settings = (new PhpMqtt\Client\ConnectionSettings)
    ->setUsername(config('mqtt.username'))
    ->setPassword(config('mqtt.password'))
    ->setKeepAliveInterval(60);

$mqtt->connect($settings, true);
echo "virtual dongle {$imei} (fw {$fromVer}) listening on {$sub}\n";

$send = function (array $extra) use ($mqtt, $pub, $imei, $fromVer) {
    $payload = array_merge([
        'IMEI' => $imei,
        'otaid' => '',
        'status' => '',
        'from_ver' => $fromVer,
        'reason' => '',
        'ts' => date('Y-m-d H:i:s'),
    ], $extra);
    $mqtt->publish($pub, json_encode($payload), 0, false);
    echo "TX {$pub}: {$payload['status']}\n";
};

$mqtt->subscribe($sub, function (string $topic, string $msg) use ($send) {
    echo "RX {$topic}: {$msg}\n";
    $cmd = json_decode($msg, true) ?: [];
    $otaid = (string) ($cmd['otaid'] ?? '');
    $ver = (string) ($cmd['ver'] ?? '0.0.0');

    $send(['otaid' => $otaid, 'status' => 'ACCEPTED']);
    sleep(3);
    $send(['otaid' => $otaid, 'status' => 'DOWNLOADING']);
    sleep(5);
    $send(['otaid' => $otaid, 'status' => 'SUCCESS', 'to_ver' => $ver]);
}, 0);

$mqtt->loop(true);
