<?php

namespace Tests\Feature;

use App\DTO\Memberships\MembershipCategoryDTO;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Memberships\MembershipCategoryRepository;
use App\Services\Memberships\MembershipCategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MembershipCategoryVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_translation_updates_advance_the_root_version_once(): void
    {
        config()->set('sync.enabled', false);
        $repository = new MembershipCategoryRepository(new MembershipCategory);
        /** @var MembershipCategory $category */
        $category = $repository->createWithTranslations([
            'gym_id' => null,
            'active' => true,
            'slug' => 'wellness',
        ], [
            'en' => [
                'name' => 'Wellness',
                'description' => 'Initial description',
            ],
        ]);
        $translation = $category->translations()->firstOrFail();

        $repository->updateWithTranslations($category->id, [
            'gym_id' => null,
            'active' => true,
            'slug' => 'wellness',
        ], [
            'en' => [
                'name' => 'Wellness updated',
                'description' => 'Updated description',
            ],
        ]);

        $this->assertSame(2, $category->fresh()->version);
        $this->assertSame(2, $translation->fresh()->version);
        $this->assertSame('Wellness updated', $translation->fresh()->name);

        $repository->updateWithTranslations($category->id, [
            'gym_id' => null,
            'active' => true,
            'slug' => 'wellness',
        ], [
            'en' => [
                'name' => 'Wellness updated',
                'description' => 'Updated description',
            ],
        ]);

        $this->assertSame(2, $category->fresh()->version);
        $this->assertSame(2, $translation->fresh()->version);
    }

    public function test_a_used_category_cannot_be_deleted_or_moved_to_another_gym(): void
    {
        config()->set('sync.enabled', false);
        $firstGym = Gym::query()->create(['name' => 'First gym']);
        $secondGym = Gym::query()->create(['name' => 'Second gym']);
        $ownerRole = Role::query()->create([
            'name' => 'owner',
            'guard_name' => 'web',
            'g_name' => 'owner',
        ]);
        $owner = User::query()->create([
            'name' => 'Owner',
            'surname' => 'Test',
            'email' => 'membership-category-owner@example.com',
            'password' => 'password',
        ]);
        $owner->assignRole($ownerRole);
        $this->actingAs($owner);

        $repository = new MembershipCategoryRepository(new MembershipCategory);
        /** @var MembershipCategory $category */
        $category = $repository->createWithTranslations([
            'gym_id' => $firstGym->id,
            'active' => true,
            'slug' => 'protected-category',
        ], [
            'en' => ['name' => 'Protected', 'description' => null],
        ]);

        DB::table('membership_plans')->insert([
            'uuid' => 'b1e988ef-372e-41a1-a938-e5e7b78b30e7',
            'version' => 1,
            'membership_category_id' => $category->id,
            'duration_type' => 'month',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(MembershipCategoryService::class);

        try {
            $service->delete($category->id);
            $this->fail('Deleting a used category should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('membership_category', $exception->errors());
        }

        try {
            $service->update($category->id, new MembershipCategoryDTO(
                gym_id: $secondGym->id,
                active: true,
                slug: 'protected-category',
                translations: [
                    'en' => ['name' => 'Protected', 'description' => null],
                ],
            ));
            $this->fail('Moving a used category should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('gym_id', $exception->errors());
        }

        $this->assertFalse($category->fresh()->trashed());
        $this->assertSame($firstGym->id, $category->fresh()->gym_id);
    }
}
