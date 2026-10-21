<?php

namespace Tests\Feature;

use App\Models\AttendanceSheet;
use App\Models\Gym;
use App\Models\Person;
use App\Models\User;
use App\Services\People\PersonService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonProfileVisitStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_counts_each_persons_entries_in_the_accessible_gym(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 12:00:00', 'Asia/Yerevan'));
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $otherGym = Gym::query()->create(['name' => 'Other gym']);
        $manager = User::query()->create([
            'name' => 'Manager', 'surname' => 'User', 'email' => 'manager@example.test',
            'password' => bcrypt('password'), 'gym_id' => $gym->id,
        ]);
        $guest = Person::query()->create(['name' => 'Guest', 'type' => 'guest']);
        $customer = Person::query()->create(['name' => 'Customer', 'type' => 'visitor']);
        $guest->gyms()->attach($gym->id);
        $customer->gyms()->attach($gym->id);

        $this->attendance($guest, $gym, '2026-09-30 23:59:00');
        $this->attendance($guest, $gym, '2026-10-01 00:00:00');
        $this->attendance($guest, $gym, '2026-10-18 10:00:00');
        $this->attendance($guest, $gym, '2026-10-18 12:00:00', 'exit');
        $this->attendance($guest, $otherGym, '2026-10-18 13:00:00');
        $this->attendance($customer, $gym, '2026-10-17 09:00:00');

        $this->actingAs($manager);
        $service = app(PersonService::class);
        $guestStats = $service->profileData($guest->id)['visitStats'];
        $customerStats = $service->profileData($customer->id)['visitStats'];

        $this->assertSame(3, $guestStats['total']);
        $this->assertSame(2, $guestStats['thisMonth']);
        $this->assertSame('2026-10-18T10:00:00+04:00', $guestStats['lastVisitAt']);
        $this->assertSame(1, $customerStats['total']);
        $this->assertSame(1, $customerStats['thisMonth']);
        $this->assertSame('2026-10-17T09:00:00+04:00', $customerStats['lastVisitAt']);
    }

    private function attendance(Person $person, Gym $gym, string $date, string $direction = 'entry'): void
    {
        AttendanceSheet::query()->create([
            'relation_type' => Person::class,
            'relation_id' => $person->id,
            'gym_id' => $gym->id,
            'date' => $date,
            'direction' => $direction,
        ]);
    }
}
