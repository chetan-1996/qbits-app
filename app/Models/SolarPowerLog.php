<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolarPowerLog extends Model
{
     protected $fillable = [
        'plant_id',
        'record_date',
        'record_time',
        'ac_momentary_power',
        'irradiation',
        'eday',
        'user_id',
        'atun',
        'atpd',
    ];
}
