<?php

namespace App\Interfaces\Discounts;

use App\Interfaces\BaseInterface;

interface DiscountInterface extends BaseInterface
{
    public function paginateForUser($user, int $perPage, array $filters = []);
    public function getWithRelations($id);
    public function createWithRelations(array $discountData, array $translations, array $membershipPlanIds);
    public function updateWithRelations(int $id, array $discountData, array $translations, array $membershipPlanIds);
}
