<?php

namespace Tests\Feature;

use App\Events\UserNotificationCreated;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\Notification;
use App\Models\Person;
use App\Models\ReminderCategory;
use App\Models\ReminderRecipient;
use App\Models\User;
use App\Services\Reminders\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_reminder_creates_one_notification_per_recipient_only_once(): void
    {
        Event::fake([UserNotificationCreated::class]);

        $gym = Gym::query()->create(['name' => 'Main gym']);
        $creator = $this->user($gym, 'Creator');
        $firstRecipient = $this->user($gym, 'First');
        $secondRecipient = $this->user($gym, 'Second');
        $person = Person::query()->create([
            'name' => 'Customer',
            'surname' => 'One',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
            'phone' => '099'.random_int(100000, 999999),
        ]);
        $category = ReminderCategory::query()->where('slug', 'general')->firstOrFail();

        $reminder = app(ReminderService::class)->create($creator, [
            'category_id' => $category->id,
            'recipient_ids' => [$firstRecipient->id, $secondRecipient->id],
            'about_id' => $person->id,
            'title' => 'Test reminder',
            'description' => 'Test description',
            'scheduled_at' => now()->subMinute(),
        ]);

        $recipientRows = ReminderRecipient::query()
            ->where('reminder_id', $reminder->id)
            ->get();

        $this->assertCount(2, $recipientRows);
        $this->assertTrue($recipientRows->every(fn (ReminderRecipient $recipient) => $recipient->uuid !== null));
        $this->assertTrue($recipientRows->every(fn (ReminderRecipient $recipient) => $recipient->version === 1));

        $this->assertSame(1, app(ReminderService::class)->sendDue());
        $this->assertSame(0, app(ReminderService::class)->sendDue());

        $this->assertDatabaseHas('reminders', [
            'id' => $reminder->id,
            'status' => 'sent',
        ]);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertEqualsCanonicalizing(
            [$firstRecipient->id, $secondRecipient->id],
            Notification::query()->pluck('recipient_id')->all(),
        );
        $this->assertSame(
            2,
            $reminder->recipients()->wherePivot('status', 'sent')->count(),
        );
        $this->assertTrue(
            ReminderRecipient::query()
                ->where('reminder_id', $reminder->id)
                ->get()
                ->every(fn (ReminderRecipient $recipient) => $recipient->version === 2),
        );
    }

    public function test_membership_source_reminders_can_be_cancelled_together(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $creator = $this->user($gym, 'Creator');
        $recipient = $this->user($gym, 'Recipient');
        $category = ReminderCategory::query()->where('slug', 'membership_payment_due')->firstOrFail();

        $reminder = app(ReminderService::class)->create($creator, [
            'category_id' => $category->id,
            'recipient_ids' => [$recipient->id],
            'source_type' => 'membership_sale',
            'source_id' => 123,
            'title' => 'Payment',
            'scheduled_at' => now()->addDay(),
        ]);

        $this->assertSame(1, app(ReminderService::class)->cancelForMembershipSale(123));
        $this->assertSame('cancelled', $reminder->fresh()->status);
        $this->assertSame(2, $reminder->fresh()->version);
        $this->assertNotNull($reminder->fresh()->cancelled_at);
    }

    public function test_payment_reminder_can_be_created_from_an_existing_membership_sale(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $creator = $this->user($gym, 'Creator');
        $recipient = $this->user($gym, 'Recipient');
        $person = Person::query()->create([
            'name' => 'Customer',
            'surname' => 'One',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
            'phone' => '098'.random_int(100000, 999999),
        ]);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'monthly',
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 100,
            'duration_type' => 'month',
            'duration_value' => 1,
        ]);
        $sale = MembershipSale::query()->create([
            'user_id' => $creator->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'total_price' => 100,
            'final_price' => 100,
            'payment_status' => 'partial',
        ]);

        $this->actingAs($creator)
            ->post("/hy/membership-sale/reminders/{$sale->id}", [
                'reminder_scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'reminder_recipient_ids' => [$recipient->id],
                'reminder_title' => 'Payment reminder',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reminders', [
            'source_type' => 'membership_sale',
            'source_id' => $sale->id,
            'about_id' => $person->id,
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('reminder_recipients', [
            'user_id' => $recipient->id,
            'status' => 'pending',
        ]);
    }

    private function user(Gym $gym, string $name): User
    {
        return User::query()->create([
            'gym_id' => $gym->id,
            'name' => $name,
            'surname' => 'User',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
        ]);
    }
}
