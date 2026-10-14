<?php

namespace App\Console\Commands;

use App\Models\DongleState;
use App\Models\OtaJob;
use App\Models\OtaJobEvent;
use App\Models\OtaJobUnit;
use App\Services\OtaMqttService;
use App\Support\SemVer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Server-side OTA dispatcher (MO-15, MO-17). Long-running daemon: sends one
 * queued command per job per tick while respecting the send gap and the
 * concurrent-download cap, applies the MO-23 timeouts, runs the MO-28
 * telemetry backstop, and closes finished jobs. Sending and tracking continue
 * with no browser open.
 *
 * Run under supervisor:  php artisan ota:run
 * Single tick for dev/tests:  php artisan ota:run --once
 */
class OtaRun extends Command
{
    protected $signature = 'ota:run {--once : Run a single tick and exit}';
    protected $description = 'Dispatch queued OTA commands, apply timeouts and the telemetry backstop';

    public function handle()
    {
        $mqtt = app(OtaMqttService::class);
        $connected = false;

        do {
            try {
                if (!$connected) {
                    $mqtt->connect(config('mqtt.client_id_prefix') . '-ota-pub-' . getmypid());
                    $connected = true;
                    $this->info('OTA dispatcher connected to broker');
                }

                $this->tick($mqtt);
            } catch (\Throwable $e) {
                Log::error('ota:run tick failed', ['error' => $e->getMessage()]);
                $connected = false;
                try {
                    $mqtt->reconnect();
                    $connected = true;
                } catch (\Throwable $re) {
                    Log::error('ota:run reconnect failed', ['error' => $re->getMessage()]);
                }
            }

            if ($this->option('once')) {
                break;
            }

            usleep(1_000_000);
        } while (true);

        $mqtt->disconnect();

        return Command::SUCCESS;
    }

    private function tick(OtaMqttService $mqtt): void
    {
        $this->sendDue($mqtt);
        $this->applyTimeouts();
        $this->telemetryBackstop();
        $this->closeFinishedJobs();
    }

    /**
     * MO-14/15: one command per unit, QoS 0 retain off, paced by the job's
     * send gap and capped by units still in flight (SENT until final/timeout).
     */
    private function sendDue(OtaMqttService $mqtt): void
    {
        $jobs = OtaJob::where('status', OtaJob::STATUS_SENDING)->get();

        foreach ($jobs as $job) {
            $inFlight = $job->units()
                ->whereIn('state', OtaJobUnit::IN_FLIGHT_STATES)
                ->count();

            if ($inFlight >= $job->max_concurrent) {
                continue;
            }

            if ($job->last_sent_at !== null
                && $job->last_sent_at->gt(now()->subSeconds($job->send_gap_seconds))) {
                continue;
            }

            $unit = $job->units()
                ->where('state', OtaJobUnit::QUEUED)
                ->orderBy('id')
                ->first();

            if ($unit === null) {
                continue;
            }

            $payload = json_encode([
                'IMEI' => $unit->imei,
                'otaid' => $job->otaid,
                'url' => $job->url,
                'ver' => $job->target_version,
                'force' => (bool) $job->force,
            ]);

            $topic = config('ota.topic_prefix') . '/' . $unit->imei . '/ota/sub';
            $mqtt->publishCommand($topic, $payload);

            // Refresh "version before" from the freshest telemetry if we have it.
            $versionBefore = $unit->version_before
                ?? DongleState::where('imei', $unit->imei)->value('fw_ver');

            $unit->update([
                'state' => OtaJobUnit::SENT,
                'version_before' => $versionBefore,
                'sent_at' => now(),
                'last_update_at' => now(),
            ]);

            $job->update(['last_sent_at' => now()]);

            Log::info('OTA command sent', ['otaid' => $job->otaid, 'imei' => $unit->imei]);
        }
    }

    /**
     * MO-23 timeouts.
     *  - SENT with no ACCEPTED/REJECTED within accept_timeout -> NO RESPONSE
     *  - ACCEPTED/DOWNLOADING with no final state within final_timeout -> TIMED OUT
     *  - NO_RESPONSE past the backstop window -> TIMED OUT (unit silent, telemetry
     *    never reached the target either; still retryable)
     */
    private function applyTimeouts(): void
    {
        $acceptBefore = now()->subSeconds(config('ota.accept_timeout_seconds'));
        $finalBefore = now()->subSeconds(config('ota.final_timeout_seconds'));

        OtaJobUnit::where('state', OtaJobUnit::SENT)
            ->where('sent_at', '<', $acceptBefore)
            ->whereHas('job', fn ($q) => $q->whereIn('status', [
                OtaJob::STATUS_SENDING, OtaJob::STATUS_PAUSED, OtaJob::STATUS_STOPPED,
            ]))
            ->update(['state' => OtaJobUnit::NO_RESPONSE, 'last_update_at' => now()]);

        OtaJobUnit::whereIn('state', [OtaJobUnit::ACCEPTED, OtaJobUnit::DOWNLOADING])
            ->where('accepted_at', '<', $finalBefore)
            ->whereHas('job', fn ($q) => $q->whereIn('status', [
                OtaJob::STATUS_SENDING, OtaJob::STATUS_PAUSED, OtaJob::STATUS_STOPPED,
            ]))
            ->update(['state' => OtaJobUnit::TIMED_OUT, 'last_update_at' => now()]);

        OtaJobUnit::where('state', OtaJobUnit::NO_RESPONSE)
            ->where('sent_at', '<', $finalBefore)
            ->whereHas('job', fn ($q) => $q->whereIn('status', [
                OtaJob::STATUS_SENDING, OtaJob::STATUS_PAUSED, OtaJob::STATUS_STOPPED,
            ]))
            ->update(['state' => OtaJobUnit::TIMED_OUT, 'last_update_at' => now()]);
    }

    /**
     * MO-28: telemetry is the backstop. A unit whose ---FWVER reaches the job's
     * target within final_timeout of SENT is marked SUCCESS "confirmed by
     * telemetry" — the only confirmation for units on firmware too old to
     * publish on ota/pub.
     */
    private function telemetryBackstop(): void
    {
        $cutoff = now()->subSeconds(config('ota.final_timeout_seconds'));

        OtaJobUnit::whereNotIn('state', OtaJobUnit::FINAL_STATES)
            ->whereNotNull('sent_at')
            ->where('sent_at', '>', $cutoff)
            ->whereHas('job', fn ($q) => $q->whereIn('status', [
                OtaJob::STATUS_SENDING, OtaJob::STATUS_PAUSED, OtaJob::STATUS_STOPPED,
            ]))
            ->with('job:id,target_version')
            ->chunkById(200, function ($units) {
                $fwVers = DongleState::whereIn('imei', $units->pluck('imei'))
                    ->pluck('fw_ver', 'imei');

                foreach ($units as $unit) {
                    $fwVer = $fwVers[$unit->imei] ?? null;
                    $target = $unit->job?->target_version;

                    if ($fwVer !== null && $target !== null && SemVer::compare($fwVer, $target) === 0) {
                        $unit->update([
                            'state' => OtaJobUnit::SUCCESS,
                            'version_after' => $fwVer,
                            'confirmed_by_telemetry' => true,
                            'last_update_at' => now(),
                        ]);
                    }
                }
            });
    }

    /**
     * An open job with no non-final units left is completed.
     */
    private function closeFinishedJobs(): void
    {
        $jobs = OtaJob::whereIn('status', [
            OtaJob::STATUS_SENDING,
            OtaJob::STATUS_PAUSED,
            OtaJob::STATUS_STOPPED,
        ])->get();

        foreach ($jobs as $job) {
            // A job still allowed to send has unfinished work by definition.
            if ($job->status === OtaJob::STATUS_SENDING
                && $job->units()->where('state', OtaJobUnit::QUEUED)->exists()) {
                continue;
            }

            $remaining = $job->units()
                ->whereNotIn('state', OtaJobUnit::FINAL_STATES)
                ->count();

            if ($remaining === 0) {
                $job->update([
                    'status' => OtaJob::STATUS_COMPLETED,
                    'finished_at' => now(),
                ]);
                OtaJobEvent::record($job, 'completed', ['counts' => $job->stateCounts()]);
            }
        }
    }
}
