<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('health-reports:dispatch')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('content:dispatch')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('business-profiles:dispatch-audits')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('search-opportunities:dispatch')
    ->hourly()
    ->withoutOverlapping();
