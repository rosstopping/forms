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

Schedule::command('content:send-suggestion-reminders')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('business-profiles:dispatch-audits')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('free-site-audits:dispatch-pending-emails')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('outreach:dispatch-due')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::command('search-opportunities:dispatch')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('seo:dispatch-weekly-snapshots')
    ->weeklyOn(1, '05:00')
    ->withoutOverlapping();

Schedule::command('search-console:sync-history')
    ->weeklyOn(1, '04:00')
    ->withoutOverlapping();

Schedule::command('ranking-reports:dispatch')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping();
