<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('memberships:update-freeze-statuses')->dailyAt('02:00');
Schedule::command('mobile-notifications:send-membership-reminders')->dailyAt('09:00');
// Schedule::command('trainer-monthly-salaries:generate')->monthlyOn(2, '02:00');
Schedule::command('trainer-monthly-salaries:generate')->everyMinute();
Schedule::command('reminders:send')->everyMinute()->withoutOverlapping();
