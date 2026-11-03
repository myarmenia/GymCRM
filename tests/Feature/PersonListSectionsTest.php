<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonListSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clients_and_newcomers_are_separate_paginated_gym_scoped_sections(): void
    {
        $gym = Gym::query()->create(['name' => 'Main']);
        $otherGym = Gym::query()->create(['name' => 'Other']);
        $user = User::factory()->create(['gym_id' => $gym->id]);
        $user->assignRole(Role::query()->firstOrCreate([
            'name' => 'manager', 'guard_name' => 'web', 'g_name' => 'manager',
        ]));

        foreach (range(1, 11) as $index) {
            $person = Person::query()->create([
                'name' => 'Visitor '.$index,
                'type' => $index === 11 ? 'guest' : 'visitor',
            ]);
            $person->gyms()->attach($gym->id);
        }
        foreach (range(1, 3) as $index) {
            $person = Person::query()->create(['name' => 'Newcomer '.$index, 'type' => 'newcomer']);
            $person->gyms()->attach($gym->id);
        }
        $outside = Person::query()->create(['name' => 'Outside', 'type' => 'newcomer']);
        $outside->gyms()->attach($otherGym->id);

        $this->actingAs($user)->get(route('person.list', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/List')
                ->where('section', 'clients')
                ->where('people.total', 11)
                ->has('people.data', 10)
                ->etc());
        $this->get(route('person.list', ['locale' => 'en', 'section' => 'clients', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.data.0.type', 'visitor')
                ->has('people.data', 1)
                ->etc());
        $this->get(route('person.list', ['locale' => 'en', 'section' => 'newcomers', 'type' => 'guest']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('section', 'newcomers')
                ->where('people.total', 3)
                ->has('people.data', 3)
                ->where('people.data.0.type', 'newcomer')
                ->etc());
    }
}
