<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryPow extends Model
{
    protected $table = 'telemetry_pow';

    protected $fillable = [
        'plant_id',
        'collector_id',
        'user_id',
        'atun',
        'atpd',
        'pow',
        'record_datetime',
    ];

    protected $casts = [
        'pow' => 'decimal:4',
        'record_datetime' => 'datetime',
    ];
}
