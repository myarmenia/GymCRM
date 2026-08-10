<?php

namespace App\Console\Commands;

use App\Services\Reminders\ReminderService;
use Illuminate\Console\Command;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Send scheduled reminders that are due';

    public function handle(ReminderService $reminderService): int
    {
        $count = $reminderService->sendDue();
        $this->info("Sent {$count} reminder(s).");

        return self::SUCCESS;
    }
}
