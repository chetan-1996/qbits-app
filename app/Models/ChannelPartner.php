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

    public function state()
    {
        return $this->belongsTo(State::class, 'state');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city');
    }
}
