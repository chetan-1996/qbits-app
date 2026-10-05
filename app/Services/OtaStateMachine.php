<?php

namespace App\Services;

use App\Models\OtaJob;
use App\Models\OtaJobUnit;
use App\Support\SemVer;

/**
 * Per-unit OTA state transitions (MO-22).
 *
 * Any status, final ones included, can be lost (QoS 0) and the same status can
 * arrive twice. Rules:
 *  - a later state replaces an earlier one
 *  - a final state is final (never overwritten)
 *  - a repeat is ignored
 *  - NO_RESPONSE / TIMED_OUT are soft: a later message still updates the row (MO-23)
 */
class OtaStateMachine
{
    // Lifecycle rank for non-final states; higher means further along.
    private const RANK = [
        OtaJobUnit::QUEUED => 0,
        OtaJobUnit::SENT => 1,
        OtaJobUnit::NO_RESPONSE => 1,
        OtaJobUnit::ACCEPTED => 2,
        OtaJobUnit::DOWNLOADING => 3,
        OtaJobUnit::TIMED_OUT => 3,
    ];

    /** Device-reported statuses we understand. */
    public const DEVICE_STATUSES = [
        'ACCEPTED',
        'REJECTED',
        'DOWNLOADING',
        'FAILED',
        'SUCCESS',
        'ROLLED_BACK',
    ];

    /**
     * Apply a device status message to a unit row.
     *
     * @param  string|null $currentFwVer  Latest firmware version seen in telemetry.
     * @return bool  Whether the row was updated.
     */
    public function apply(
        OtaJobUnit $unit,
        OtaJob $job,
        string $status,
        ?string $reason,
        ?string $detail,
        ?string $unitTs,
        ?string $fromVer,
        ?string $toVer,
        ?string $currentFwVer
    ): bool {
        // A final state is final.
        if ($unit->isFinal()) {
            return false;
        }

        $target = $this->mapStatus($status, $unit, $job, $reason, $currentFwVer);
        if ($target === null) {
            return false;
        }

        // A repeat is ignored.
        if ($unit->state === $target) {
            return false;
        }

        $currentRank = self::RANK[$unit->state] ?? -1;
        $targetRank = self::RANK[$target] ?? null;

        if ($targetRank !== null) {
            // Non-final incoming state: must advance the row. Timeout rows accept
            // anything because a later message still updates the row (MO-23).
            $timedOut = in_array($unit->state, [OtaJobUnit::NO_RESPONSE, OtaJobUnit::TIMED_OUT], true);
            if (!$timedOut && $targetRank <= $currentRank) {
                return false;
            }
        }
        // Final incoming state always wins over a non-final row.

        $attrs = [
            'state' => $target,
            'reason' => $reason,
            'detail' => $detail,
            'unit_ts' => $unitTs ?: null,
            'last_update_at' => now(),
        ];

        // from_ver is the version that took the command — authoritative for
        // "version before" when telemetry had nothing fresher.
        if ($fromVer !== null && $fromVer !== '') {
            $attrs['version_before'] = $fromVer;
        }

        if ($target === OtaJobUnit::ACCEPTED) {
            $attrs['accepted_at'] = now();
        }

        if ($target === OtaJobUnit::SUCCESS) {
            // to_ver on SUCCESS is the version now running.
            if ($toVer !== null && $toVer !== '') {
                $attrs['version_after'] = $toVer;
                // MO-27: installed image is not the version the job named
                if ($job->target_version !== null
                    && SemVer::compare($toVer, $job->target_version) !== 0) {
                    $attrs['version_differs'] = true;
                }
            }
        }

        $unit->update($attrs);

        return true;
    }

    /**
     * Map a device status string onto a unit state, or null to ignore it.
     */
    private function mapStatus(
        string $status,
        OtaJobUnit $unit,
        OtaJob $job,
        ?string $reason,
        ?string $currentFwVer
    ): ?string {
        return match ($status) {
            'ACCEPTED' => OtaJobUnit::ACCEPTED,
            'DOWNLOADING' => OtaJobUnit::DOWNLOADING,
            'FAILED' => OtaJobUnit::FAILED,
            'SUCCESS' => OtaJobUnit::SUCCESS,
            'ROLLED_BACK' => OtaJobUnit::ROLLED_BACK,
            'REJECTED' => $this->mapRejected($unit, $job, $reason, $currentFwVer),
            default => null,
        };
    }

    /**
     * REJECTED with VERSION_NOT_NEWER while the unit already reports the
     * target version shows as UP TO DATE (states table, section 4).
     */
    private function mapRejected(OtaJobUnit $unit, OtaJob $job, ?string $reason, ?string $currentFwVer): string
    {
        if ($reason === 'VERSION_NOT_NEWER'
            && $currentFwVer !== null
            && $job->target_version !== null
            && SemVer::compare($currentFwVer, $job->target_version) === 0) {
            return OtaJobUnit::UP_TO_DATE;
        }

        return OtaJobUnit::REJECTED;
    }
}
