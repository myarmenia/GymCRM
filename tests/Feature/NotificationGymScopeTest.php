<?php

namespace Tests\Feature;

use App\Events\UserNotificationCreated;
use App\Models\Gym;
use App\Models\Notification;
use App\Models\Person;
use App\Models\ReminderCategory;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Reminders\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationGymScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_options_and_send_to_all_are_limited_to_the_senders_gym(): void
    {
        Event::fake([UserNotificationCreated::class]);
        [$sender, $local, $other, $localPerson, $otherPerson] = $this->records();

        $this->assertSame([$local->id], app(NotificationService::class)->usersForSelect($sender)->pluck('value')->all());
        $this->assertSame([$localPerson->id], app(NotificationService::class)->peopleForSelect($sender)->pluck('value')->all());
        $this->assertEqualsCanonicalizing(
            [$sender->id, $local->id],
            app(ReminderService::class)->usersForSelect($sender)->pluck('value')->all(),
        );

        $this->actingAs($sender)
            ->get('/hy/notifications/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Create')
                ->where('users.0.value', $local->id)
                ->missing('users.1')
                ->where('people.0.value', $localPerson->id)
                ->missing('people.1')
                ->etc());

        $this->actingAs($sender)
            ->post('/hy/notifications', ['send_to_all' => 1, 'title' => 'Gym notice'])
            ->assertSessionHasNoErrors();

        $this->assertSame([$local->id], Notification::query()->pluck('recipient_id')->all());
    }

    public function test_foreign_recipients_and_customers_cannot_be_submitted(): void
    {
        [$sender, $local, $other, $localPerson, $otherPerson] = $this->records();

        $this->actingAs($sender)
            ->post('/hy/notifications', [
                'send_to_all' => 0,
                'recipient_ids' => [$other->id],
                'about_id' => $otherPerson->id,
                'title' => 'Wrong gym',
            ])
            ->assertSessionHasErrors(['recipient_ids', 'about_id']);

        $this->assertDatabaseCount('notifications', 0);

        $category = ReminderCategory::query()->firstOrCreate(
            ['slug' => 'general'],
            ['name' => 'General', 'active' => true],
        );

        $this->actingAs($sender)
            ->post('/hy/reminders', [
                'category_id' => $category->id,
                'recipient_ids' => [$other->id],
                'about_id' => $otherPerson->id,
                'scheduled_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertSessionHasErrors(['recipient_ids', 'about_id']);

        $this->assertDatabaseCount('reminders', 0);
    }

    private function records(): array
    {
        $firstGym = Gym::query()->create(['name' => 'First gym']);
        $secondGym = Gym::query()->create(['name' => 'Second gym']);

        $sender = User::factory()->create(['gym_id' => $firstGym->id, 'name' => 'Sender']);
        $local = User::factory()->create(['gym_id' => $firstGym->id, 'name' => 'Local']);
        $other = User::factory()->create(['gym_id' => $secondGym->id, 'name' => 'Other']);

        $localPerson = $this->person($firstGym);
        $otherPerson = $this->person($secondGym);

        return [$sender, $local, $other, $localPerson, $otherPerson];
    }

    private function person(Gym $gym): Person
    {
        $person = Person::query()->create([
            'name' => 'Customer',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'phone' => '+374'.random_int(10000000, 99999999),
        ]);
        $person->gyms()->attach($gym->id);

        return $person;
    }
}
