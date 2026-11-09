<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keeps analog_calendar topped up automatically -- cheap to run daily
// since it's a no-op unless the table is within 60 days of running out
// (see App\Console\Commands\GenerateAnalogCalendarYear). This only fires
// if something is actually invoking Laravel's scheduler
// (`php artisan schedule:run`) on a timer -- XAMPP/Windows has no cron
// daemon for that by default, so a Windows Task Scheduler entry still
// needs to be set up separately to call it (see the command's own
// docblock for the two ways to do that).
Schedule::command('calendar:generate-year')->daily();
