<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtaJobUnit extends Model
{
    // Progress states
    public const QUEUED = 'QUEUED';
    public const SENT = 'SENT';
    public const ACCEPTED = 'ACCEPTED';
    public const DOWNLOADING = 'DOWNLOADING';

    // Final states
    public const SUCCESS = 'SUCCESS';
    public const UP_TO_DATE = 'UP_TO_DATE';
    public const REJECTED = 'REJECTED';
    public const FAILED = 'FAILED';
    public const ROLLED_BACK = 'ROLLED_BACK';
    public const REPLACED = 'REPLACED';

    // Timeout states (a later message may still update the row)
    public const NO_RESPONSE = 'NO_RESPONSE';
    public const TIMED_OUT = 'TIMED_OUT';

    // Pre-send exclusion states
    public const INVALID = 'INVALID';
    public const SKIPPED = 'SKIPPED';

    public const FINAL_STATES = [
        self::SUCCESS,
        self::UP_TO_DATE,
        self::REJECTED,
        self::FAILED,
        self::ROLLED_BACK,
        self::REPLACED,
        self::INVALID,
        self::SKIPPED,
    ];

    // Unit counts toward the concurrency cap from SENT until final or timeout
    public const IN_FLIGHT_STATES = [
        self::SENT,
        self::ACCEPTED,
        self::DOWNLOADING,
    ];

    public const RETRYABLE_STATES = [
        self::NO_RESPONSE,
        self::TIMED_OUT,
        self::FAILED,
    ];

    protected $fillable = [
        'ota_job_id',
        'imei',
        'site',
        'note',
        'version_before',
        'version_after',
        'state',
        'reason',
        'detail',
        'unit_ts',
        'attempt',
        'version_differs',
        'confirmed_by_telemetry',
        'sent_at',
        'accepted_at',
        'last_update_at',
    ];

    protected $casts = [
        'version_differs' => 'boolean',
        'confirmed_by_telemetry' => 'boolean',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'last_update_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(OtaJob::class, 'ota_job_id');
    }

    public function isFinal(): bool
    {
        return in_array($this->state, self::FINAL_STATES, true);
    }

    public function isInFlight(): bool
    {
        return in_array($this->state, self::IN_FLIGHT_STATES, true);
    }

    public function isRetryable(): bool
    {
        return in_array($this->state, self::RETRYABLE_STATES, true);
    }
}
