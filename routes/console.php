<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('contents:publish-due')->everyMinute();
Schedule::command('notifications:send-due')->everyMinute();
Schedule::command('reminders:send-programs')->everyMinute();
Schedule::command('reminders:send-inactivity')->everyMinute();
