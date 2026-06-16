<?php

namespace App\Interfaces\Memberships;

use App\Interfaces\BaseInterface;
use Illuminate\Database\Eloquent\Model;


interface MembershipPlanInterface extends BaseInterface
{
    // public function paginateForUser($user, int $perPage);
    public function getCreateData();

    public function store(array $data);

    public function edit(int $id);

    public function update(int $id, array $data): Model;

    public function findForEdit(int $id, string $locale): array;
}
