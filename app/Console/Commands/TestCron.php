<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestCron extends Command
{
    protected $signature = 'test:cron';
    protected $description = 'This is a test cron that runs every minute';

    public function handle(): void
    {
        Log::info('✅ TestCron executed successfully at ' . now());
        $this->info('✅ TestCron executed successfully at ' . now());
    }
}
