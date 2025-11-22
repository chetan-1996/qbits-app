<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'dealer_id',
        'qbits_company_code',
        'company_name',
        'company_code',
        'username',
        'password',
        'phone',
        'qq',
        'email',
        'collector',
        'plant_name',
        'inverter_type',
        'city_name',
        'longitude',
        'latitude',
        'parent',
        'gmt',
        'plant_type',
        'iserial',
        'whatsapp_notification_flag',
        'inverter_fault_flag',
        'daily_generation_report_flag',
        'weekly_generation_report_flag',
        'monthly_generation_report_flag',
    ];
}
