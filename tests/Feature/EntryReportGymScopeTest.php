<?php

namespace Tests\Feature;

use App\Models\AttendanceSheet;
use App\Models\Gym;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\EntryReports\EntryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EntryReportGymScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_only_include_people_linked_to_the_attendance_gym(): void
    {
        $firstGym = Gym::query()->create(['name' => 'First gym']);
        $secondGym = Gym::query()->create(['name' => 'Second gym']);
        $firstPerson = $this->person('first@example.test', [$firstGym->id]);
        $secondPerson = $this->person('second@example.test', [$secondGym->id]);
        $sharedPerson = $this->person('shared@example.test', [$firstGym->id, $secondGym->id]);

        $firstReport = $this->attendance($firstPerson, $firstGym->id);
        $sharedFirstReport = $this->attendance($sharedPerson, $firstGym->id);
        $secondReport = $this->attendance($secondPerson, $secondGym->id);
        $sharedSecondReport = $this->attendance($sharedPerson, $secondGym->id);
        $wrongGymReport = $this->attendance($secondPerson, $firstGym->id);
        $this->attendance($firstPerson, $secondGym->id);
        $staff = $this->user('staff@example.test', $firstGym->id, 'manager');
        $this->attendance($staff, $firstGym->id);

        $service = app(EntryReportService::class);
        $manager = $this->user('manager@example.test', $firstGym->id, 'manager');
        $this->actingAs($manager);

        $page = $service->indexData([]);
        $this->assertEqualsCanonicalizing(
            [$firstReport->id, $sharedFirstReport->id],
            collect($page['reports']->items())->pluck('id')->all(),
        );
        $this->assertSame(2, $page['summary']['total_count']);
        $this->assertSame(2, $page['summary']['people_count']);
        $this->assertSame(0, $page['summary']['users_count']);
        $this->assertEqualsCanonicalizing(
            [$firstReport->id, $sharedFirstReport->id],
            $service->exportRows([])->pluck('id')->all(),
        );
        $this->assertSame($firstReport->id, $service->showData($firstReport)['id']);

        foreach ([$secondReport, $wrongGymReport] as $hiddenReport) {
            try {
                $service->showData($hiddenReport);
                $this->fail('A report outside the manager gym must not be visible.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $owner = $this->user('owner@example.test', null, 'owner');
        $this->actingAs($owner);
        $ownerPage = $service->indexData(['client_id' => $secondGym->id]);
        $this->assertEqualsCanonicalizing(
            [$secondReport->id, $sharedSecondReport->id],
            collect($ownerPage['reports']->items())->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$secondReport->id, $sharedSecondReport->id],
            $service->exportRows(['client_id' => $secondGym->id])->pluck('id')->all(),
        );
    }

    public function test_manager_without_gym_cannot_view_all_reports(): void
    {
        $this->actingAs($this->user('unassigned@example.test', null, 'manager'));

        $this->expectException(HttpException::class);
        app(EntryReportService::class)->indexData([]);
    }

    public function test_super_admin_only_sees_customers_of_their_assigned_gym(): void
    {
        $firstGym = Gym::query()->create(['name' => 'First gym']);
        $secondGym = Gym::query()->create(['name' => 'Second gym']);
        $firstPerson = $this->person('first@example.test', [$firstGym->id]);
        $secondPerson = $this->person('second@example.test', [$secondGym->id]);
        $firstReport = $this->attendance($firstPerson, $firstGym->id);
        $secondReport = $this->attendance($secondPerson, $secondGym->id);

        $this->actingAs($this->user('super@example.test', $firstGym->id, 'super_admin'));
        $service = app(EntryReportService::class);

        $page = $service->indexData(['client_id' => $secondGym->id]);
        $this->assertFalse($page['canSelectClient']);
        $this->assertSame(1, $page['summary']['total_count']);
        $this->assertSame([$firstReport->id], collect($page['reports']->items())->pluck('id')->all());
        $this->assertSame(
            [$firstReport->id],
            $service->exportRows(['client_id' => $secondGym->id])->pluck('id')->all(),
        );
        $this->assertSame($firstReport->id, $service->showData($firstReport)['id']);

        try {
            $service->showData($secondReport);
            $this->fail('A super admin must not view another gym report.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_super_admin_without_gym_cannot_view_all_reports(): void
    {
        $this->actingAs($this->user('unassigned-super@example.test', null, 'super_admin'));

        $this->expectException(HttpException::class);
        app(EntryReportService::class)->indexData([]);
    }

    private function person(string $email, array $gymIds): Person
    {
        $person = Person::query()->create([
            'name' => 'Test',
            'surname' => 'Person',
            'email' => $email,
            'password' => bcrypt('password'),
            'phone' => '+37499'.str_pad((string) (Person::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'type' => 'visitor',
        ]);
        $person->gyms()->attach($gymIds);

        return $person;
    }

    private function user(string $email, ?int $gymId, string $role): User
    {
        $user = User::query()->create([
            'name' => 'Test',
            'surname' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'gym_id' => $gymId,
        ]);
        $user->assignRole(Role::query()->firstOrCreate([
            'name' => $role,
            'guard_name' => 'web',
            'g_name' => $role,
        ]));

        return $user;
    }

    private function attendance(Person|User $owner, int $gymId): AttendanceSheet
    {
        return AttendanceSheet::query()->create([
            'relation_type' => $owner::class,
            'relation_id' => $owner->id,
            'gym_id' => $gymId,
            'entry_code' => 'TEST',
            'date' => '2026-09-28 10:00:00',
            'direction' => 'entry',
            'type' => 'manual',
        ]);
    }
}
