<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelPartner extends Model
{
    protected $fillable = [
        'photo',
        'name',
        'company_name',
        'designation',
        'mobile',
        'whatsapp_no',
        'address',
        'state',
        'city',
        'latitude',
        'longitude'
    ];
}
