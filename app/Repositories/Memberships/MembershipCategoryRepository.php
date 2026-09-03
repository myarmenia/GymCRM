<?php

namespace App\Repositories\Memberships;

use App\Interfaces\Memberships\MembershipCategoryInterface;
use App\Models\MembershipCategory;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

class MembershipCategoryRepository extends BaseRepository implements MembershipCategoryInterface
{
    public function __construct(MembershipCategory $model)
    {
        parent::__construct($model);
    }

    public function getAllForSelectByGymId()
    {
        return $this->gymQuery()->get(['id', 'slug','active']);
    }

    // Ձեր արդեն գոյություն ունեցող մեթոդը
    public function allActive()
    {
        return $this->gymQuery()->active()->get();
    }

    // ===== NEW METHODS =====
    public function paginateForUser($user, int $perPage = 15)
    {
        $query = $this->query()->with('translations');
        if (!$user->hasRole('owner')) {
            $query->where(function ($q) use ($user) {
                $q->where('gym_id', $user->gym_id)->orWhereNull('gym_id');
            });
        }
        return $query->paginate($perPage);
    }

    public function createWithTranslations(array $categoryData, array $translations): MembershipCategory
    {
        return DB::connection($this->model->getConnectionName())->transaction(function () use (
            $categoryData,
            $translations,
        ): MembershipCategory {
            /** @var MembershipCategory $category */
            $category = $this->create($categoryData);

            foreach ($translations as $locale => $data) {
                $category->translations()->create([
                    'locale' => $locale,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                ]);
            }

            return $category->load('translations');
        });
    }

    public function updateWithTranslations(int $id, array $categoryData, array $translations): MembershipCategory
    {
        return DB::connection($this->model->getConnectionName())->transaction(function () use (
            $id,
            $categoryData,
            $translations,
        ): MembershipCategory {
            /** @var MembershipCategory $category */
            $category = $this->query()->lockForUpdate()->findOrFail($id);
            $startingVersion = (int) $category->version;
            $category->fill($categoryData);
            $aggregateChanged = $category->isDirty();

            foreach ($translations as $locale => $data) {
                $translation = $category->translations()->firstOrNew([
                    'locale' => $locale,
                ]);
                $translation->fill([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                ]);

                if (! $translation->exists || $translation->isDirty()) {
                    $translation->save();
                    $aggregateChanged = true;
                }
            }

            if ($aggregateChanged) {
                $category->version = $startingVersion + 1;
                $category->save();
            }

            return $category->fresh('translations');
        });
    }

    public function getWithTranslations($id)
    {
        return $this->findOrFail($id, ['translations']);
    }
}
