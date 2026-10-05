<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DongleState extends Model
{
    protected $fillable = [
        'imei',
        'last_seen_at',
        'fw_ver',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    /**
     * Record a data/heartbeat packet from a dongle: refresh last_seen_at
     * and the running firmware version when the payload carried one.
     */
    public static function recordSeen(string $imei, ?string $fwVer = null): void
    {
        if ($imei === '') {
            return;
        }

        $attrs = ['last_seen_at' => now()];
        if ($fwVer !== null && $fwVer !== '') {
            $attrs['fw_ver'] = $fwVer;
        }

        static::updateOrCreate(['imei' => $imei], $attrs);
    }
}
