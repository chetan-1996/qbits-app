<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InverterFault extends Model
{
    protected $fillable = [
        'inverter_id',
        'plant_id',
        'status',
        'itype',
        'inverter_sn',
        'stime',
        'etime',
        'meta',
        'message_cn',
        'message_en',
        'atun',
        'atpd',
        'user_id'
    ];

    protected $casts = [
        'meta'       => 'array',
        'message_cn' => 'array',
        'message_en' => 'array',
        'stime'      => 'datetime',
        'etime'      => 'datetime',
    ];
}
