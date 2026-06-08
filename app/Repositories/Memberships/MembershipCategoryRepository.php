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
        DB::beginTransaction();
        try {
            $category = $this->create($categoryData);
            foreach ($translations as $locale => $data) {
                $category->translations()->create([
                    'locale' => $locale,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                ]);
            }
            DB::commit();
            return $category;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateWithTranslations(int $id, array $categoryData, array $translations): MembershipCategory
    {
        DB::beginTransaction();
        try {
            $category = $this->update($id, $categoryData);
            foreach ($translations as $locale => $data) {
                $category->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                    ]
                );
            }
            DB::commit();
            return $category;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getWithTranslations($id)
    {
        return $this->findOrFail($id, ['translations']);
    }
}