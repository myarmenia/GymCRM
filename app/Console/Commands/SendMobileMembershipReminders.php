<?php

namespace App\Console\Commands;

use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use App\Services\MobileNotifications\MobilePushNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMobileMembershipReminders extends Command
{
    protected $signature = 'mobile-notifications:send-membership-reminders {--date= : Date used as today (Y-m-d)}';

    protected $description = 'Send mobile reminders for membership and freeze expiration dates.';

    public function handle(MobilePushNotificationService $notifications): int
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : today();
        $membershipDate = $today->copy()->addDays(3)->toDateString();
        $freezeDate = $today->copy()->addDays(2)->toDateString();
        $membershipCount = 0;
        $freezeCount = 0;

        PersonMembership::query()
            ->with('person')
            ->whereIn('status', ['active', 'frozen'])
            ->whereDate('valid_at', $membershipDate)
            ->chunkById(200, function ($memberships) use ($notifications, &$membershipCount) {
                foreach ($memberships as $membership) {
                    if ($notifications->sendMembershipExpiresInThreeDays($membership)?->wasRecentlyCreated) {
                        $membershipCount++;
                    }
                }
            });

        PersonMembershipFreeze::query()
            ->with('personMembership.person')
            ->whereDate('end_date', $freezeDate)
            ->whereHas('personMembership', fn ($query) => $query->whereIn('status', ['active', 'frozen']))
            ->chunkById(200, function ($freezes) use ($notifications, &$freezeCount) {
                foreach ($freezes as $freeze) {
                    if ($notifications->sendFreezeEndsInTwoDays($freeze)?->wasRecentlyCreated) {
                        $freezeCount++;
                    }
                }
            });

        $this->info("Mobile reminders created. Memberships: {$membershipCount}, freezes: {$freezeCount}.");

        return self::SUCCESS;
    }
}
