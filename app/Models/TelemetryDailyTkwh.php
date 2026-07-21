<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemetryDailyTkwh extends Model
{
    protected $table = 'telemetry_daily_tkwh';

    protected $fillable = [
        'plant_id',
        'collector_id',
        'record_date',
        'tkwh',
        'user_id',
        'atun',
        'atpd',
    ];

    protected $casts = [
        'record_date' => 'date',
        'tkwh' => 'decimal:4',
    ];
}
