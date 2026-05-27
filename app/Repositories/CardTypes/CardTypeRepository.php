<?php

namespace App\Repositories\CardTypes;

use App\Interfaces\CardTypes\CardTypeInterface;
use App\Models\CardType;
use App\Repositories\BaseRepository;

class CardTypeRepository extends BaseRepository implements CardTypeInterface
{

    public function __construct(CardType $model)
    {
        parent::__construct($model);
    }


}
