<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtaJobEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ota_job_id',
        'actor_role',
        'actor_ip',
        'event',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(OtaJob::class, 'ota_job_id');
    }

    public static function record(OtaJob $job, string $event, array $meta = [], ?string $role = null, ?string $ip = null): self
    {
        return static::create([
            'ota_job_id' => $job->id,
            'actor_role' => $role,
            'actor_ip' => $ip,
            'event' => $event,
            'meta' => $meta ?: null,
        ]);
    }
}
