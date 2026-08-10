<?php

namespace App\Services\MobileNotifications;

use App\Models\MobileNotification;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use App\Services\Notifications\FirebasePushNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class MobilePushNotificationService
{
    public function __construct(
        protected FirebasePushNotificationService $firebase,
    ) {}

    public function sendFirstLogin(Person $person): ?MobileNotification
    {
        return $this->sendOnce(
            $person,
            'first-login:'.$person->id,
            'welcome',
            'Բարի գալուստ FitTracker',
            'Ձեր հաշիվը պատրաստ է։ Սկսեք հետևել մարզումներին և աբոնեմենտին։',
            ['screen' => 'home'],
        );
    }

    public function sendMembershipPurchased(PersonMembership $membership): ?MobileNotification
    {
        return $this->sendOnce(
            $membership->person,
            'membership-purchased:'.$membership->id,
            'membership_purchased',
            'Աբոնեմենտը գնված է',
            'Ձեր նոր աբոնեմենտը հաջողությամբ ավելացվել է։',
            [
                'screen' => 'membership',
                'membership_id' => $membership->id,
            ],
        );
    }

    public function sendMembershipExpiresInThreeDays(PersonMembership $membership): ?MobileNotification
    {
        return $this->sendOnce(
            $membership->person,
            'membership-expires-in-3-days:'.$membership->id.':'.$membership->valid_at?->toDateString(),
            'membership_expiring',
            'Աբոնեմենտի ավարտին մնացել է 3 օր',
            'Ձեր աբոնեմենտի ժամկետը շուտով կավարտվի։',
            [
                'screen' => 'membership',
                'membership_id' => $membership->id,
                'expires_at' => $membership->valid_at?->toDateString(),
                'days_remaining' => 3,
            ],
        );
    }

    public function sendFreezeEndsInTwoDays(PersonMembershipFreeze $freeze): ?MobileNotification
    {
        $membership = $freeze->personMembership;

        return $this->sendOnce(
            $membership->person,
            'membership-freeze-ends-in-2-days:'.$freeze->id.':'.$freeze->end_date?->toDateString(),
            'membership_freeze_ending',
            'Սառեցման ավարտին մնացել է 2 օր',
            'Ձեր աբոնեմենտի սառեցումը շուտով կավարտվի։',
            [
                'screen' => 'membership',
                'membership_id' => $membership->id,
                'freeze_id' => $freeze->id,
                'freeze_ends_at' => $freeze->end_date?->toDateString(),
                'days_remaining' => 2,
            ],
        );
    }

    protected function sendOnce(
        Person $person,
        string $deduplicationKey,
        string $type,
        string $title,
        string $body,
        array $data,
    ): ?MobileNotification {
        try {
            $notification = MobileNotification::query()->firstOrCreate(
                ['deduplication_key' => $deduplicationKey],
                [
                    'person_id' => $person->id,
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                ],
            );

            if (! $notification->wasRecentlyCreated || blank($person->fcm_token)) {
                return $notification;
            }

            $result = $this->firebase->sendToPerson($person, $title, $body, [
                ...$data,
                'notification_id' => $notification->id,
                'type' => $type,
            ]);

            if (($result['token_should_be_removed'] ?? false) === true) {
                $person->forceFill(['fcm_token' => null])->save();
            }

            return $notification;
        } catch (Throwable $exception) {
            Log::error('Mobile notification could not be created or delivered.', [
                'person_id' => $person->id,
                'deduplication_key' => $deduplicationKey,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
