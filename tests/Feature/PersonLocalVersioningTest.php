<?php

namespace Tests\Feature;

use App\DTO\People\PersonDTO;
use App\Http\Requests\People\StorePersonRequest;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PersonLocalVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_only_fields_do_not_advance_the_shared_version(): void
    {
        $person = Person::query()->create([
            'name' => 'Person',
            'surname' => 'Test',
            'email' => 'mobile-version@example.com',
            'password' => bcrypt('password'),
            'phone' => '+37499000001',
            'type' => 'visitor',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
        ]);

        $person->update([
            'image' => 'people/images/local.jpg',
            'fcm_token' => 'mobile-device-token',
            'mobile_deleted' => true,
        ]);

        $this->assertSame(1, $person->fresh()->version);
    }

    public function test_mobile_shared_profile_change_advances_the_version(): void
    {
        $person = Person::query()->create([
            'name' => 'Person',
            'surname' => 'Test',
            'email' => 'mobile-profile@example.com',
            'password' => bcrypt('password'),
            'phone' => '+37499000002',
            'type' => 'visitor',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
        ]);

        $person->update([
            'passport_series' => 'AB1234567',
            'passport_issued_by' => 'Police department',
            'passport_issued_at' => '2020-01-15',
            'address' => 'Yerevan',
        ]);

        $this->assertSame(2, $person->fresh()->version);
        $this->assertSame('AB1234567', $person->fresh()->passport_series);
        $this->assertSame('Police department', $person->fresh()->passport_issued_by);
        $this->assertSame('2020-01-15', $person->fresh()->passport_issued_at);
        $this->assertSame('Yerevan', $person->fresh()->address);
    }

    public function test_admin_dto_excludes_mobile_owned_fields(): void
    {
        $payload = PersonDTO::fromArray([
            'name' => 'Person',
            'email' => 'dto@example.com',
            'password' => 'password',
            'phone' => '+37499000003',
            'type' => 'visitor',
            'birth_date' => '1990-01-01',
            'mobile_deleted' => true,
            'fcm_token' => 'must-not-pass',
            'passport_series' => 'AN7654321',
            'passport_issued_by' => 'Passport office',
            'passport_issued_at' => '2021-03-20',
            'address' => 'Gyumri',
        ])->toArray();

        $this->assertArrayNotHasKey('mobile_deleted', $payload);
        $this->assertArrayNotHasKey('fcm_token', $payload);
        $this->assertSame('AN7654321', $payload['passport_series']);
        $this->assertSame('Passport office', $payload['passport_issued_by']);
        $this->assertSame('2021-03-20', $payload['passport_issued_at']);
        $this->assertSame('Gyumri', $payload['address']);
    }

    public function test_other_gender_is_rejected_by_admin_validation(): void
    {
        $rule = (new StorePersonRequest)->rules()['gender'];
        $validator = Validator::make(['gender' => 'other'], ['gender' => $rule]);

        $this->assertTrue($validator->fails());
    }
}
