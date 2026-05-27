<?php

namespace App\Interfaces\Documents;

use App\Interfaces\BaseInterface;

interface DocumentInterface extends BaseInterface
{
    public function getByOwner(int|string $ownerId, string $ownerType): array;
}
