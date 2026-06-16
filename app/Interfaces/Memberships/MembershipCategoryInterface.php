<?php

namespace App\Interfaces\Memberships;

use App\Interfaces\BaseInterface;

interface MembershipCategoryInterface extends BaseInterface
{
    public function allActive();
    // Interface-ում ավելացնել
    public function paginateForUser($user, int $perPage);
    public function getWithTranslations($id);
    public function createWithTranslations(array $categoryData, array $translations);
    public function updateWithTranslations(int $id, array $categoryData, array $translations);
}
