<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| A scheduled article only goes live when this runs, so the scheduler itself must be
| running in every environment that serves real traffic — `php artisan schedule:work`
| locally, a cron entry or the platform's scheduler in production. Overlap protection
| because the command writes; it is a no-op on the vast majority of minutes.
*/
Schedule::command('articles:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();
