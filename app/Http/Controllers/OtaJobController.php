<?php

namespace App\Http\Controllers;

use App\Exports\OtaJobUnitsExport;
use App\Http\Middleware\OtaAuth;
use App\Imports\OtaDeviceListImport;
use App\Models\Dongle;
use App\Models\DongleState;
use App\Models\Firmware;
use App\Models\OtaJob;
use App\Models\OtaJobEvent;
use App\Models\OtaJobUnit;
use App\Support\SemVer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class OtaJobController extends Controller
{
    /**
     * Job list.
     */
    public function index()
    {
        $jobs = OtaJob::orderByDesc('id')->paginate(20);
        $counts = [];
        foreach ($jobs as $job) {
            $counts[$job->id] = $job->stateCounts();
        }

        return view('ota.index', compact('jobs', 'counts'));
    }

    /**
     * Upload form (MO-01).
     */
    public function create()
    {
        return view('ota.create', [
            'maxUnits' => config('ota.max_units'),
        ]);
    }

    /**
     * Downloadable list template (MO-01).
     */
    public function template()
    {
        $csv = "IMEI,Site,Note\n865056070026808,Example site,First unit\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ota_device_list_template.csv"',
        ]);
    }

    /**
     * Parse the uploaded file, create a draft job and show the preview (MO-02..06).
     */
    public function preview(Request $request)
    {
        $request->validate([
            // extensions, not mimes: Windows/PHP guesses CSV content as
            // text/plain -> txt and mimes:csv would reject real CSV files.
            'file' => 'required|file|extensions:xlsx,xls,csv',
        ]);

        $import = new OtaDeviceListImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            Log::error('OTA device list import failed', ['error' => $e->getMessage()]);
            return redirect()->route('ota.jobs.create')
                ->with('error', 'Could not read the file: ' . $e->getMessage());
        }

        if ($import->fatalError !== null) {
            return redirect()->route('ota.jobs.create')->with('error', $import->fatalError);
        }

        if (count($import->items) === 0) {
            return redirect()->route('ota.jobs.create')
                ->with('error', 'The file contains no device rows.');
        }

        $valid = array_filter($import->items, fn ($i) => $i['error'] === null);
        if (count($valid) > config('ota.max_units')) {
            return redirect()->route('ota.jobs.create')
                ->with('error', 'A job is limited to ' . config('ota.max_units')
                    . ' units; the file contains ' . count($valid) . ' valid rows.');
        }

        $role = OtaAuth::role($request);

        $job = DB::transaction(function () use ($import, $role, $request, $valid) {
            $job = OtaJob::create([
                'otaid' => $this->generateOtaid(),
                'status' => OtaJob::STATUS_DRAFT,
                'send_gap_seconds' => config('ota.send_gap_seconds'),
                'max_concurrent' => config('ota.max_concurrent'),
                'created_by_role' => $role,
                'created_by_ip' => $request->ip(),
            ]);

            $fwVers = DongleState::whereIn(
                'imei',
                array_map(fn ($i) => $i['imei'], array_filter($import->items, fn ($i) => $i['error'] === null))
            )->pluck('fw_ver', 'imei');

            foreach ($import->items as $item) {
                $job->units()->create([
                    'imei' => $item['imei'] !== '' ? $item['imei'] : '(row ' . $item['row'] . ')',
                    'site' => $item['site'],
                    'note' => $item['note'],
                    'version_before' => $item['error'] === null ? ($fwVers[$item['imei']] ?? null) : null,
                    'state' => $item['error'] === null ? OtaJobUnit::QUEUED : OtaJobUnit::INVALID,
                    'detail' => $item['error'],
                ]);
            }

            OtaJobEvent::record($job, 'created', [
                'rows' => count($import->items),
                'valid' => count($valid),
            ], $role, $request->ip());

            return $job;
        });

        return redirect()->route('ota.jobs.show', $job);
    }

    /**
     * Job page: draft jobs render the preview/send form, running jobs the
     * live results table (MO-21).
     */
    public function show(OtaJob $job, Request $request)
    {
        $job->load('units');

        if ($job->status === OtaJob::STATUS_DRAFT) {
            return $this->showPreview($job, $request);
        }

        return view('ota.show', [
            'job' => $job,
            'counts' => $job->stateCounts(),
            'events' => $job->events()->orderByDesc('created_at')->get(),
            'isAdmin' => OtaAuth::role($request) === OtaAuth::ROLE_ADMIN,
        ]);
    }

    /**
     * JSON rows + counts for live polling (MO-21).
     */
    public function rows(OtaJob $job)
    {
        return response()->json([
            'status' => $job->status,
            'counts' => $job->stateCounts(),
            'units' => $job->units()->orderBy('id')->get()->map(fn ($u) => [
                'id' => $u->id,
                'imei' => $u->imei,
                'site' => $u->site,
                'note' => $u->note,
                'version_before' => $u->version_before,
                'version_after' => $u->version_after,
                'state' => $u->state,
                'reason' => $u->reason,
                'detail' => $u->detail,
                'unit_ts' => $u->unit_ts,
                'attempt' => $u->attempt,
                'version_differs' => $u->version_differs,
                'confirmed_by_telemetry' => $u->confirmed_by_telemetry,
                'last_update_at' => optional($u->last_update_at)->toDateTimeString(),
                'retryable' => $u->isRetryable(),
            ]),
        ]);
    }

    /**
     * Confirm and start sending (MO-10..15, MO-44).
     */
    public function send(OtaJob $job, Request $request)
    {
        if ($job->status !== OtaJob::STATUS_DRAFT) {
            return redirect()->route('ota.jobs.show', $job)
                ->with('error', 'This job has already been sent.');
        }

        $role = OtaAuth::role($request);

        $request->validate([
            'firmware_id' => ['required', 'exists:firmwares,id'],
            'otaid' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,32}$/', 'unique:ota_jobs,otaid,' . $job->id],
            'send_gap_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'max_concurrent' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $firmware = Firmware::findOrFail($request->input('firmware_id'));
        $force = $request->boolean('force');
        $includeUpToDate = $request->boolean('include_uptodate');

        // MO-13: the 9.9.9 backup image is an administrator-only action.
        if (($firmware->is_backup || $firmware->version === config('ota.backup_version'))
            && $role !== OtaAuth::ROLE_ADMIN) {
            return redirect()->route('ota.jobs.show', $job)
                ->with('error', 'The backup image (' . config('ota.backup_version') . ') is an administrator-only action.');
        }

        // MO-12: force is administrators only and needs a typed confirmation.
        if ($force) {
            if ($role !== OtaAuth::ROLE_ADMIN) {
                return redirect()->route('ota.jobs.show', $job)
                    ->with('error', 'Only administrators can use force.');
            }
            if ($request->input('force_confirm') !== 'FORCE') {
                return redirect()->route('ota.jobs.show', $job)
                    ->with('error', 'Type FORCE to confirm a forced update (allows downgrade / reinstall).');
            }
        }

        // MO-05: units already on the target version are skipped unless included.
        $queuedUnits = $job->units()->where('state', OtaJobUnit::QUEUED)->get();
        $fwVers = DongleState::whereIn('imei', $queuedUnits->pluck('imei'))->pluck('fw_ver', 'imei');

        DB::transaction(function () use ($job, $request, $firmware, $force, $includeUpToDate, $queuedUnits, $fwVers, $role) {
            foreach ($queuedUnits as $unit) {
                $fwVer = $fwVers[$unit->imei] ?? null;
                if (!$includeUpToDate && $fwVer !== null
                    && SemVer::compare($fwVer, $firmware->version) === 0) {
                    $unit->update([
                        'state' => OtaJobUnit::UP_TO_DATE,
                        'version_before' => $fwVer,
                        'version_after' => $fwVer,
                    ]);
                }
            }

            $job->update([
                'firmware_id' => $firmware->id,
                'target_version' => $firmware->version,
                'url' => $firmware->deviceUrl(),
                'otaid' => $request->input('otaid'),
                'force' => $force,
                'send_gap_seconds' => $request->input('send_gap_seconds', config('ota.send_gap_seconds')),
                'max_concurrent' => $request->input('max_concurrent', config('ota.max_concurrent')),
                'status' => OtaJob::STATUS_SENDING,
                'confirmed_at' => now(),
            ]);

            OtaJobEvent::record($job, 'sent', [
                'target_version' => $firmware->version,
                'firmware' => $firmware->filename,
                'url' => $job->url,
                'force' => $force,
                'counts' => $job->stateCounts(),
            ], $role, $request->ip());
        });

        return redirect()->route('ota.jobs.show', $job)
            ->with('success', 'Job ' . $job->fresh()->otaid . ' is sending.');
    }

    public function pause(OtaJob $job, Request $request)
    {
        if ($job->status === OtaJob::STATUS_SENDING) {
            $job->update(['status' => OtaJob::STATUS_PAUSED]);
            OtaJobEvent::record($job, 'paused', [], OtaAuth::role($request), $request->ip());

            return redirect()->route('ota.jobs.show', $job)
                ->with('success', 'Job paused — queued units will not be sent until resumed. Units already sent carry on.');
        }

        return redirect()->route('ota.jobs.show', $job)
            ->with('error', 'Only a sending job can be paused (current: ' . $job->status . ').');
    }

    public function resume(OtaJob $job, Request $request)
    {
        if ($job->status === OtaJob::STATUS_PAUSED) {
            $job->update(['status' => OtaJob::STATUS_SENDING]);
            OtaJobEvent::record($job, 'resumed', [], OtaAuth::role($request), $request->ip());

            return redirect()->route('ota.jobs.show', $job)
                ->with('success', 'Job resumed.');
        }

        return redirect()->route('ota.jobs.show', $job)
            ->with('error', 'Only a paused job can be resumed (current: ' . $job->status . ').');
    }

    /**
     * MO-16: stop sends nothing further; units already sent carry on.
     */
    public function stop(OtaJob $job, Request $request)
    {
        if (in_array($job->status, [OtaJob::STATUS_SENDING, OtaJob::STATUS_PAUSED], true)) {
            $skipped = $job->units()->where('state', OtaJobUnit::QUEUED)
                ->update(['state' => OtaJobUnit::SKIPPED, 'last_update_at' => now()]);
            $job->update(['status' => OtaJob::STATUS_STOPPED]);
            OtaJobEvent::record($job, 'stopped', ['skipped' => $skipped], OtaAuth::role($request), $request->ip());

            return redirect()->route('ota.jobs.show', $job)
                ->with('success', "Job stopped — {$skipped} queued unit(s) skipped. Units already sent carry on.");
        }

        return redirect()->route('ota.jobs.show', $job)
            ->with('error', 'This job can no longer be stopped (current: ' . $job->status . ').');
    }

    /**
     * MO-24: retry selected rows in NO RESPONSE, TIMED OUT or FAILED as a new
     * attempt under the same job.
     */
    public function retry(OtaJob $job, Request $request)
    {
        $request->validate([
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['integer'],
        ]);

        $units = $job->units()
            ->whereIn('id', $request->input('unit_ids'))
            ->whereIn('state', OtaJobUnit::RETRYABLE_STATES)
            ->get();

        if ($units->isEmpty()) {
            return redirect()->route('ota.jobs.show', $job)
                ->with('error', 'No retryable units selected (only NO RESPONSE, TIMED OUT or FAILED).');
        }

        DB::transaction(function () use ($job, $units, $request) {
            foreach ($units as $unit) {
                $unit->update([
                    'state' => OtaJobUnit::QUEUED,
                    'attempt' => $unit->attempt + 1,
                    'reason' => null,
                    'detail' => null,
                    'unit_ts' => null,
                    'version_after' => null,
                    'version_differs' => false,
                    'confirmed_by_telemetry' => false,
                    'sent_at' => null,
                    'accepted_at' => null,
                ]);
            }

            // Re-open the job so the dispatcher picks the queued retries up.
            if (in_array($job->status, [OtaJob::STATUS_COMPLETED, OtaJob::STATUS_STOPPED, OtaJob::STATUS_PAUSED], true)) {
                $job->update(['status' => OtaJob::STATUS_SENDING]);
            }

            OtaJobEvent::record($job, 'retry', [
                'units' => $units->pluck('imei')->all(),
            ], OtaAuth::role($request), $request->ip());
        });

        return redirect()->route('ota.jobs.show', $job)
            ->with('success', $units->count() . ' unit(s) queued for retry.');
    }

    /**
     * Current firmware + last-seen per dongle (dongle_states, fed by every
     * data/heartbeat ---FWVER key). Lets the operator check which version a
     * given IMEI is running outside of any job.
     */
    public function devices(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $states = DongleState::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('imei', 'like', '%' . $q . '%')
                    ->orWhereIn('imei',
                        Dongle::where('dongle_id', 'like', '%' . $q . '%')->select('imei'));
            })
            ->orderByDesc('last_seen_at')
            ->paginate(50)
            ->withQueryString();

        $dongleIds = Dongle::whereIn('imei', $states->pluck('imei'))->pluck('dongle_id', 'imei');
        $offlineAfter = now()->subHours(config('ota.offline_after_hours'));

        return view('ota.devices', [
            'states' => $states,
            'dongleIds' => $dongleIds,
            'offlineAfter' => $offlineAfter,
            'q' => $q,
        ]);
    }

    /**
     * MO-25: export the results table to Excel.
     */
    public function export(OtaJob $job)
    {
        return Excel::download(new OtaJobUnitsExport($job), 'ota-' . $job->otaid . '.xlsx');
    }

    private function showPreview(OtaJob $job, Request $request)
    {
        $units = $job->units()->orderBy('id')->get();
        $imeis = $units->where('state', OtaJobUnit::QUEUED)->pluck('imei');

        $states = DongleState::whereIn('imei', $imeis)->get()->keyBy('imei');
        $knownImeis = Dongle::whereIn('imei', $imeis)->pluck('imei')->flip();
        $offlineAfter = now()->subHours(config('ota.offline_after_hours'));

        $rows = $units->map(function ($u) use ($states, $knownImeis, $offlineAfter) {
            $st = $states[$u->imei] ?? null;
            return [
                'unit' => $u,
                'fw_ver' => $st?->fw_ver ?? $u->version_before,
                'last_seen' => $st?->last_seen_at,
                'never_seen' => $st === null || $st->last_seen_at === null,
                'known' => $knownImeis->has($u->imei),
                'offline' => $st !== null && $st->last_seen_at !== null && $st->last_seen_at->lt($offlineAfter),
            ];
        });

        return view('ota.preview', [
            'job' => $job,
            'rows' => $rows,
            'firmwares' => Firmware::orderBy('version')->get(),
            'isAdmin' => OtaAuth::role($request) === OtaAuth::ROLE_ADMIN,
            'defaultGap' => config('ota.send_gap_seconds'),
            'defaultConcurrent' => config('ota.max_concurrent'),
            'backupVersion' => config('ota.backup_version'),
        ]);
    }

    /**
     * OTA-YYYYMMDD-NNNN, unique, charset and length per MO-11.
     */
    private function generateOtaid(): string
    {
        $prefix = 'OTA-' . now()->format('Ymd') . '-';
        $seq = OtaJob::where('otaid', 'like', $prefix . '%')->count() + 1;

        do {
            $otaid = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (OtaJob::where('otaid', $otaid)->exists());

        return $otaid;
    }
}
