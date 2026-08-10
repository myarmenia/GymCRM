<?php

namespace Tests\Feature;

use App\Models\MobileNotification;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use App\Services\MobileNotifications\MobilePushNotificationService;
use App\Services\Notifications\FirebasePushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MobilePushNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_business_notification_is_created_and_sent_only_once(): void
    {
        $person = Person::query()->create([
            'name' => 'Mobile',
            'surname' => 'User',
            'email' => 'mobile-user@example.com',
            'password' => bcrypt('password'),
            'phone' => '+37400000000',
            'type' => 'visitor',
            'fcm_token' => 'test-fcm-token',
        ]);

        $firebase = Mockery::mock(FirebasePushNotificationService::class);
        $firebase->shouldReceive('sendToPerson')
            ->times(4)
            ->andReturn(['success' => true, 'reason' => 'sent']);

        $service = new MobilePushNotificationService($firebase);

        $membership = (new PersonMembership([
            'valid_at' => today()->addDays(3)->toDateString(),
        ]))->setAttribute('id', 10)->setRelation('person', $person);

        $freeze = (new PersonMembershipFreeze([
            'end_date' => today()->addDays(2)->toDateString(),
        ]))->setAttribute('id', 20)->setRelation('personMembership', $membership);

        foreach ([
            fn () => $service->sendFirstLogin($person),
            fn () => $service->sendMembershipPurchased($membership),
            fn () => $service->sendMembershipExpiresInThreeDays($membership),
            fn () => $service->sendFreezeEndsInTwoDays($freeze),
        ] as $send) {
            $send();
            $send();
        }

        $this->assertSame(4, MobileNotification::query()->count());
        $this->assertSame([
            'membership_expiring',
            'membership_freeze_ending',
            'membership_purchased',
            'welcome',
        ], MobileNotification::query()->orderBy('type')->pluck('type')->all());
    }

    public function test_invalid_fcm_token_is_removed_from_person(): void
    {
        $person = Person::query()->create([
            'name' => 'Mobile',
            'email' => 'invalid-token@example.com',
            'password' => bcrypt('password'),
            'phone' => '+37400000001',
            'fcm_token' => 'invalid-fcm-token',
        ]);

        $firebase = Mockery::mock(FirebasePushNotificationService::class);
        $firebase->shouldReceive('sendToPerson')->once()->andReturn([
            'success' => false,
            'reason' => 'invalid_token',
            'token_should_be_removed' => true,
        ]);

        (new MobilePushNotificationService($firebase))->sendFirstLogin($person);

        $this->assertNull($person->fresh()->fcm_token);
    }
}
