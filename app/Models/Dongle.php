<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dongle extends Model
{
    use HasFactory;

    protected $fillable = [
        'dongle_id',
        'imei',
        'imsi',
        'sim_num',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];
}
