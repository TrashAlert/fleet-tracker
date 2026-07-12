<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| In Laravel 12, the scheduler is defined here.
| Run locally with:  php artisan schedule:work
| In production add this single cron entry:
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
*/

// Offline detection — every minute, alert vehicles silent > threshold
Schedule::command('fleet:check-offline')->everyMinute();

// Delay detection — every minute, flip overdue started shipments to delayed and
// alert clients even when the vehicle has stopped sending GPS packets.
Schedule::command('fleet:check-delays')->everyMinute();
