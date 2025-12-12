<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InverterFaultService;

class SyncInverterFaults extends Command
{
    protected $signature = 'faults:sync';
    protected $description = 'Sync inverter faults every minute with low memory usage';

    public function handle(InverterFaultService $service)
    {
        $start = microtime(true);

        $this->info("⏳ Running fault sync...");

        $service->sync();

        $time = round(microtime(true) - $start, 2);

        $this->info("✅ Fault sync completed in {$time}s");

        return 0;
    }
}
