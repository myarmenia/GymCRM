<?php

namespace App\Services\Memberships;

use App\DTO\Memberships\MembershipCategoryDTO;
use App\Interfaces\Memberships\MembershipCategoryInterface;
use Illuminate\Support\Facades\Auth;

class MembershipCategoryService
{
    public function __construct(protected MembershipCategoryInterface $membershipCategoryRepository)
    {
    }

    // ✅ արդեն գոյություն ունեցող մեթոդը
    public function getActiveCategories()
    {
        return $this->membershipCategoryRepository->active();
    }

    // ========== NEW METHODS ==========

    /**
     * Get all categories with pagination, respecting user roles
     */
    public function getAllPaginated(int $perPage = 15)
    {
        $user = Auth::user();
        return $this->membershipCategoryRepository->paginateForUser($user, $perPage);
    }

    /**
     * Get a single category by ID with translations
     */
    public function getById(int $id)
    {
        return $this->membershipCategoryRepository->getWithTranslations($id);
    }

    /**
     * Create a new category with translations
     */
    public function store(MembershipCategoryDTO $dto)
    {
        $data = [
            'gym_id' => $dto->gym_id,
            'active' => $dto->active,
            'slug'   => $dto->slug,
        ];
        return $this->membershipCategoryRepository->createWithTranslations($data, $dto->translations);
    }

    /**
     * Update an existing category and its translations
     */
    public function update(int $id, MembershipCategoryDTO $dto)
    {
        $data = array_filter([
            'gym_id' => $dto->gym_id,
            'active' => $dto->active,
            'slug'   => $dto->slug,
        ], fn($v) => !is_null($v));

        return $this->membershipCategoryRepository->updateWithTranslations($id, $data, $dto->translations);
    }

    /**
     * Delete a category (soft delete)
     */
    public function delete(int $id): bool
    {
        return $this->membershipCategoryRepository->delete($id);
    }
}