<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
Schedule::command('inverterWeeklyGeneration:cron')->weeklyOn(1, '4:15')->runInBackground()->withoutOverlapping();
Schedule::command('inverterMonthlyGeneration:cron')->monthlyOn(1, '4:45')->runInBackground()->withoutOverlapping();
Schedule::command('inverterDailyGeneration:cron')->dailyAt('20:00')->runInBackground()->withoutOverlapping();
Schedule::command('inverterFault:cron')->twiceDaily(10, 16)->runInBackground()->withoutOverlapping();
Schedule::command('test:cron')->everyMinute();
