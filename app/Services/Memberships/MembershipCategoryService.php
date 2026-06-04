<?php

namespace App\Services\Memberships;

use App\Interfaces\Memberships\MembershipCategoryInterface;

class MembershipCategoryService
{

    public function __construct(protected MembershipCategoryInterface $membershipCategoryRepository)
    {
    }


    public function getActiveCategories()
    {
        return $this->membershipCategoryRepository->active();
    }







}
