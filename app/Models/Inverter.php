<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inverter extends Model
{
    protected $fillable = [
        'id',
        'inverter_no',
        'inverter_address',
        'collector_address',
        'model',
        'state',
        'control',
        'register0a',
        'register31',
        'register29',
        'register2a',
        'remark1',
        'remark2',
        'remark3',
        'remark4',
        'room_id',
        'plant_id',
        'timezone',
        'record_time',
        'inverter_type',
        'load',
        'panel',
        'panel_num',
    ];

    /**
     * Optional casting (recommended)
     */
    protected $casts = [
        'state'             => 'integer',
        'control'           => 'integer',
        'register0a'        => 'integer',
        'register31'        => 'integer',
        'register29'        => 'integer',
        'register2a'        => 'integer',
        'room_id'           => 'integer',
        'plant_id'          => 'integer',
        'timezone'          => 'integer',
        'inverter_type'     => 'integer',
        'load'              => 'integer',
        'panel'             => 'integer',
        'panel_num'         => 'integer',
    ];

    /**
     * Latest inverter detail (FAST & OPTIMIZED)
     */
    public function latestDetail()
{
    return $this->hasOne(InverterDetail::class, 'inverterId', 'id')
        ->ofMany('recordTime', 'max'); // ✅ NOT latestOfMany
}

    /**
     * All inverter history
     */
    public function detail()
    {
        return $this->hasOne(InverterDetail::class, 'inverterId', 'id');
    }

    public function plant()
{
    return $this->belongsTo(PlantInfo::class, 'plant_id', 'plant_no');
}
}
