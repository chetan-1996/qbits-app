<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mass OTA update settings (AL0228 wireless dongle)
    |--------------------------------------------------------------------------
    */

    // MQTT topic prefix: <prefix>/<IMEI>/ota/sub and <prefix>/<IMEI>/ota/pub
    'topic_prefix' => env('OTA_TOPIC_PREFIX', 'rtsg-1/Ongridrooftop'),

    // Operator password for the OTA portal (admins use ADMIN_PASSWORD).
    'operator_password' => env('OTA_OPERATOR_PASSWORD'),

    // Public HTTPS base URL the dongles download firmware from (Let's Encrypt cert).
    'firmware_base_url' => env('OTA_FIRMWARE_BASE_URL') ?: env('APP_URL'),

    // Pacing defaults (MO-15)
    'send_gap_seconds' => (int) env('OTA_SEND_GAP', 10),
    'max_concurrent'   => (int) env('OTA_MAX_CONCURRENT', 10),

    // Hard cap on units per job (MO-06)
    'max_units' => (int) env('OTA_JOB_MAX_UNITS', 500),

    // Timeouts (MO-23)
    'accept_timeout_seconds' => (int) env('OTA_ACCEPT_TIMEOUT', 300),   // SENT -> NO RESPONSE
    'final_timeout_seconds'  => (int) env('OTA_FINAL_TIMEOUT', 2700),  // ACCEPTED -> TIMED OUT

    // Reserved backup image version; never allowed in a normal job (MO-13)
    'backup_version' => '9.9.9',

    // Unit is considered offline if not seen for this long (MO-04)
    'offline_after_hours' => (int) env('OTA_OFFLINE_AFTER_HOURS', 24),
];
