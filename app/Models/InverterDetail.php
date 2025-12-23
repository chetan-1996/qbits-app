<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InverterDetail extends Model
{
    protected $table = 'inverter_details';

    // Primary key from API
    protected $primaryKey = 'inverterId';

    // Not auto-incrementing
    public $incrementing = false;

    protected $keyType = 'int';

    // Enable timestamps
    public $timestamps = true;

    /**
     * Allow mass assignment for ALL columns
     */
    protected $fillable = [
        'inverterId',
        'recordTime',
        'inverterState',
        'onlineHours',
        'onlineMinutes',
        'onlineSeconds',

        'alarmInfor1',
        'alarmInfor2',
        'alarmInfor3',
        'alarmInfor4',
        'alarmInfor5',
        'alarmInfor6',
        'alarmInfor7',
        'alarmInfory1',
        'alarmInfory2',
        'alarmInfory3',
        'alarmInfory4',
        'alarmInfory5',
        'alarmInfory6',
        'alarmInfory7',

        'dayMpp',
        'mpptVoltage',
        'acVoltage',
        'acBvoltage',
        'acCvoltage',
        'acCurrent',
        'acBcurrent',
        'acCcurrent',
        'acFrequency',
        'acMomentaryPower',
        'reactivePower',

        'dcMomentaryPower',
        'dcMomentaryPower2',
        'dcMomentaryPower3',
        'dcMomentaryPower4',
        'dcMomentaryPower5',
        'dcMomentaryPower6',

        'dcCurrent',
        'dcVoltage',
        'dcCurrent2',
        'dcVoltage2',
        'dcCurrent3',
        'dcVoltage3',
        'dcCurrent4',
        'dcVoltage4',
        'dcCurrent5',
        'dcVoltage5',
        'dcCurrent6',
        'dcVoltage6',
        'dcVoltageMuxian',
        'dcVoltageMuxian2',

        'dayPowerHigh',
        'dayPowerLower',
        'totalPowerHigh',
        'totalPowerLower',

        'temperature',
        'temperaturedc',
        'powerFactor',
        'co2',
        'iv',
        'angui',

        'plantId',
        'keepLive',
        'signal',

        'powerSet',
        'repowerSet',
        'protocolV',
        'deviceType',
        'currentSwitch',

        'current1',
        'current2',
        'current3',
        'current4',
        'current5',
        'current6',
        'current7',
        'current8',
        'current9',
        'current10',
        'current11',
        'current12',
        'current13',
        'current14',
        'current15',
        'current16',

        'inverterSn',
        'inverterNo',
        'outputStatus',

        'meterPower',
        'meterTotal',
        'meterRetotal',
    ];

    /**
     * Belongs to inverter
     */
     public function inverter()
    {
        return $this->belongsTo(Inverter::class, 'inverterId', 'id');
    }
}
