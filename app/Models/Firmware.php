<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Firmware extends Model
{
    protected $table = 'firmwares';

    protected $fillable = [
        'version',
        'filename',
        'path',
        'size',
        'sha256',
        'notes',
        'is_backup',
        'uploaded_by_role',
    ];

    protected $casts = [
        'is_backup' => 'boolean',
        'size' => 'integer',
    ];

    /**
     * Public HTTPS URL the dongle downloads from (built from the
     * configured firmware base URL, not necessarily APP_URL).
     */
    public function deviceUrl(): string
    {
        return rtrim(config('ota.firmware_base_url'), '/')
            . '/firmware/download/' . $this->filename;
    }
}
