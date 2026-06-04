<?php

namespace App\Repositories\SubCategory;

use App\Interfaces\Category\CategoryInterface;
use App\Interfaces\SubCategory\SubCategoryInterface;
use App\Models\InventoryCategory;
use App\Repositories\BaseRepository;

class SubCategoryRepository extends BaseRepository implements SubCategoryInterface
{

    public function __construct(InventoryCategory $model)
    {
        parent::__construct($model);
    }

}
