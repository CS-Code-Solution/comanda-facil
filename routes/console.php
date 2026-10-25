<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('operations:publish')->everySecond()->withoutOverlapping(1);
if (config('pilot.scheduled_backup')) {
    Schedule::command('operations:backup')->dailyAt(config('pilot.backup_time'))->timezone(config('pilot.timezone'))->withoutOverlapping(60);
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
