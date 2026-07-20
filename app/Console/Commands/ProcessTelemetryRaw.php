<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTelemetryRaw as ProcessTelemetryRawJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessTelemetryRaw extends Command
{
    protected $signature = 'telemetry:process-raw
                            {--batch=500 : Number of records per job}
                            {--collector= : Process specific collector only}';

    protected $description = 'Process telemetry_raw for clients with server_flag=1 into inverter_logs and inverter_latest';

    public function handle(): int
    {
        DB::disableQueryLog();

        $batchSize = (int) $this->option('batch');
        $specificCollector = $this->option('collector');

        if ($specificCollector) {
            $this->info("Processing collector: {$specificCollector}");

            $client = DB::table('clients')
                ->where('collector', $specificCollector)
                ->select('id', 'collector', 'company_name', 'username', 'password')
                ->first();

            ProcessTelemetryRawJob::dispatch($specificCollector, $client, $batchSize);
            $this->info('Job dispatched.');
            return Command::SUCCESS;
        }

        $clients = DB::table('clients')
            ->where('server_flag', 1)
            ->whereNotNull('collector')
            ->select('id', 'collector', 'company_name', 'username', 'password')
            ->orderBy('id')
            ->get();

        if ($clients->isEmpty()) {
            $this->warn('No clients with server_flag=1 found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$clients->count()} clients with server_flag=1.");

        $pendingCounts = DB::table('telemetry_raw')
            ->whereNull('processed_at')
            ->whereIn('collector_id', $clients->pluck('collector')->toArray())
            ->select('collector_id', DB::raw('count(*) as pending'))
            ->groupBy('collector_id')
            ->pluck('pending', 'collector_id');

        foreach ($clients as $client) {
            $pending = $pendingCounts[$client->collector] ?? 0;

            if ($pending === 0) {
                $this->line("  <comment>Skipped</comment> {$client->company_name} ({$client->collector}) — no pending records.");
                continue;
            }

            ProcessTelemetryRawJob::dispatch($client->collector, $client, $batchSize);
            $this->info("  Dispatched job for {$client->company_name} ({$client->collector}) — {$pending} pending records.");
        }

        Log::info('Telemetry raw processing jobs dispatched', [
            'clients'    => $clients->count(),
            'batch_size' => $batchSize,
        ]);

        $this->info('Done. Jobs dispatched to queue.');
        return Command::SUCCESS;
    }
}
