<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('memberships:update-freeze-statuses')->everyMinute()->withoutOverlapping();
Schedule::command('mobile-notifications:send-membership-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('trainer-monthly-salaries:generate')->everyMinute()->withoutOverlapping();
Schedule::command('reminders:send')->everyMinute()->withoutOverlapping();
