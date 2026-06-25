<?php

namespace App\Interfaces\People;

use App\Interfaces\BaseInterface;

interface PersonInterface extends BaseInterface
{

    public function getPeopleByGymId(int $gymId);

    public function getPeopleByGymIdForSelect(int $gymId);
}
