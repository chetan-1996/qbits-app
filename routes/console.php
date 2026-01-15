<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

 // Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote');
Schedule::command('inverterWeeklyGeneration:cron')->weeklyOn(1, '3:15')->runInBackground()->withoutOverlapping();
Schedule::command('inverterMonthlyGeneration:cron')->monthlyOn(1, '4:45')->runInBackground()->withoutOverlapping();
Schedule::command('inverterDailyGeneration:cron')
    ->everyThirtyMinutes()
    ->between('19:00', '23:00')
    ->runInBackground()
    ->withoutOverlapping();
//Schedule::command('inverterDailyGeneration:cron')->dailyAt('19:00')->runInBackground()->withoutOverlapping();
// Schedule::command('inverterFault:cron')->twiceDaily(10, 16)->runInBackground()->withoutOverlapping();
Schedule::command('inverterFault:cron')->hourly()->between('08:00', '18:00')->runInBackground()->withoutOverlapping();
// Schedule::command('getInverterStatus:cron')->everyThirtyMinutes()->between('08:00', '19:00')->runInBackground()->withoutOverlapping();
// Schedule::command('plantInfo:cron')->everyThirtyMinutes()->between('08:00', '19:00')->runInBackground()->withoutOverlapping();
// Schedule::command('faults:sync')->everyThirtyMinutes()->between('08:00', '19:00')->runInBackground()->withoutOverlapping();
Schedule::command('getInverterStatus:cron')->cron('0,30 8-19 * * *')->runInBackground()->withoutOverlapping();
Schedule::command('plantInfo:cron')->cron('0,30 8-19 * * *')->runInBackground()->withoutOverlapping();
Schedule::command('faults:sync')->cron('0,30 8-19 * * *')->runInBackground()->withoutOverlapping();
// Schedule::command('plantInfo:cron')->cron('10,40 8-19 * * *')->runInBackground()->withoutOverlapping();
// Schedule::command('faults:sync')->cron('20,50 8-19 * * *')->runInBackground()->withoutOverlapping();
//Schedule::command('test:cron')->everyMinute();
