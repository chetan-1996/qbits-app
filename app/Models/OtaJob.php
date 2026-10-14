<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OtaJob extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENDING = 'sending';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'otaid',
        'firmware_id',
        'target_version',
        'url',
        'force',
        'status',
        'send_gap_seconds',
        'max_concurrent',
        'created_by_role',
        'created_by_ip',
        'last_sent_at',
        'confirmed_at',
        'finished_at',
    ];

    protected $casts = [
        'force' => 'boolean',
        'last_sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(OtaJobUnit::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OtaJobEvent::class);
    }

    public function firmware(): BelongsTo
    {
        return $this->belongsTo(Firmware::class);
    }

    /**
     * Jobs whose sends or tracking are still live. A stopped job keeps
     * tracking units that already received the command.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENDING,
            self::STATUS_PAUSED,
            self::STATUS_STOPPED,
        ], true);
    }

    public function stateCounts(): array
    {
        return $this->units()
            ->selectRaw('state, COUNT(*) as n')
            ->groupBy('state')
            ->pluck('n', 'state')
            ->toArray();
    }
}
