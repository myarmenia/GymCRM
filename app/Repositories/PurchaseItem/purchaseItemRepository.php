<?php

namespace App\Repositories\PurchaseItem;

use App\Interfaces\Purchase\PurchaseInterface;
use App\Interfaces\PurchaseItem\PurchaseItemInterface;
use App\Models\PurchaseItem;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;




class PurchaseItemRepository extends BaseRepository implements PurchaseItemInterface
{

    public function __construct(PurchaseItem $model)
    {
        parent::__construct($model);
    }
}
