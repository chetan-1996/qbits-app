<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlantInfo extends Model
{
    use HasFactory;

    protected $table = 'plant_infos';

    protected $fillable = [
        'plant_no',
        'plant_name',

        'is_room',
        'is_enviroment',
        'is_blackflow',

        'elec_subsidy_price',
        'internet_power_price',
        'own_power_price',
        'internet_power_occupy',
        'own_power_occupy',

        'remark1',
        'remark2',
        'remark3',
        'remark4',
        'remark5',
        'remark6',

        'plant_user',

        'acpower',
        'eday',
        'etot',

        'plantstate',
        'planttype',

        'record_time',

        'capacity',
        'capacitybattery',

        'country',
        'province',
        'city',
        'district',

        'month_power',
        'year_power',
        'power_rate',
        'kpi',
        'date',
        'watch',
        'time',

        'user_id',
        'atun',
        'atpd',

        'full_response'
    ];

    protected $casts = [
        'is_room'        => 'boolean',
        'is_enviroment'  => 'boolean',
        'is_blackflow'   => 'boolean',

        'acpower'        => 'decimal:2',
        'eday'           => 'decimal:2',
        'etot'           => 'decimal:2',

        'capacity'       => 'decimal:2',
        'capacitybattery'=> 'decimal:2',

        'month_power'    => 'decimal:2',
        'year_power'     => 'decimal:2',
        'power_rate'     => 'decimal:2',
        'kpi'            => 'decimal:2',

        'watch'          => 'boolean',
        'time'           => 'datetime',

        'full_response'  => 'array', // auto decode JSON
    ];

    // Relationship with User (if needed)
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
