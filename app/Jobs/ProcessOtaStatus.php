<?php

namespace App\Jobs;

use App\Models\DongleState;
use App\Models\OtaJob;
use App\Models\OtaJobEvent;
use App\Models\OtaJobUnit;
use App\Services\OtaStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Applies one ota/pub status message to the matching job unit row (MO-20).
 * Matching: by otaid + IMEI; a REJECTED for a malformed id carries "otaid": ""
 * and is matched to the unit by IMEI and the most recently sent open row.
 */
class ProcessOtaStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public int $backoff = 5;

    public function __construct(
        public string $topic,
        public string $message,
        public string $receivedAt
    ) {}

    public function handle(OtaStateMachine $machine): void
    {
        $payload = json_decode($this->message, true);
        if (!is_array($payload)) {
            return;
        }

        $imei = isset($payload['IMEI']) ? trim((string) $payload['IMEI']) : '';
        $status = strtoupper(trim((string) ($payload['status'] ?? '')));

        if ($imei === '' || !in_array($status, OtaStateMachine::DEVICE_STATUSES, true)) {
            return;
        }

        // The unit is alive — refresh last seen (and firmware if a later join needs it).
        try {
            DongleState::recordSeen($imei);
        } catch (\Throwable $e) {
            Log::debug('OTA status: dongle state touch failed', ['error' => $e->getMessage()]);
        }

        $otaid = trim((string) ($payload['otaid'] ?? ''));
        $unit = $this->findUnit($imei, $otaid);

        if ($unit !== null) {
            $job = $unit->job;
            $currentFwVer = DongleState::where('imei', $imei)->value('fw_ver');

            $machine->apply(
                $unit,
                $job,
                $status,
                $payload['reason'] ?? null,
                $payload['detail'] ?? null,
                $payload['ts'] ?? null,
                isset($payload['from_ver']) ? (string) $payload['from_ver'] : null,
                isset($payload['to_ver']) ? (string) $payload['to_ver'] : null,
                $currentFwVer
            );
        } else {
            Log::info('OTA status with no matching job row', [
                'imei' => $imei,
                'otaid' => $otaid,
                'status' => $status,
            ]);
        }

        // MO-18: ACCEPTED for a newer otaid replaces open rows in other jobs.
        if ($status === 'ACCEPTED') {
            $this->replaceOtherOpenJobs($imei, $unit?->ota_job_id);
        }

        $this->closeJobIfFinished($unit);
    }

    private function findUnit(string $imei, string $otaid): ?OtaJobUnit
    {
        if ($otaid !== '') {
            return OtaJobUnit::where('imei', $imei)
                ->whereNotIn('state', [OtaJobUnit::INVALID, OtaJobUnit::SKIPPED])
                ->whereHas('job', fn ($q) => $q->where('otaid', $otaid))
                ->orderBy('id')
                ->first();
        }

        // Malformed-id REJECTED carries "otaid": "" — match by IMEI + send time.
        return OtaJobUnit::where('imei', $imei)
            ->whereNotNull('sent_at')
            ->whereNotIn('state', OtaJobUnit::FINAL_STATES)
            ->orderByDesc('sent_at')
            ->first();
    }

    private function replaceOtherOpenJobs(string $imei, ?int $exceptJobId): void
    {
        $rows = OtaJobUnit::where('imei', $imei)
            ->whereNotIn('state', OtaJobUnit::FINAL_STATES)
            ->where('ota_job_id', '!=', $exceptJobId ?? 0)
            ->whereHas('job', fn ($q) => $q->whereIn('status', [
                OtaJob::STATUS_SENDING,
                OtaJob::STATUS_PAUSED,
                OtaJob::STATUS_STOPPED,
            ]))
            ->get();

        foreach ($rows as $row) {
            $row->update([
                'state' => OtaJobUnit::REPLACED,
                'reason' => 'NEWER_OTAID',
                'last_update_at' => now(),
            ]);
        }
    }

    /**
     * Mark the job completed when no non-final units remain (MO-26 audit).
     */
    private function closeJobIfFinished(?OtaJobUnit $unit): void
    {
        if ($unit === null) {
            return;
        }

        $job = $unit->job()->with('units')->first();
        if ($job === null || !$job->isOpen()) {
            return;
        }

        $remaining = $job->units()->whereNotIn('state', OtaJobUnit::FINAL_STATES)->count();
        if ($remaining > 0) {
            return;
        }

        $job->update([
            'status' => OtaJob::STATUS_COMPLETED,
            'finished_at' => now(),
        ]);

        OtaJobEvent::record($job, 'completed', ['counts' => $job->stateCounts()]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessOtaStatus job failed', [
            'error' => $e->getMessage(),
            'topic' => $this->topic,
            'message' => $this->message,
        ]);
    }
}
