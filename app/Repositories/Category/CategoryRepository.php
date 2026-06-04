<?php

namespace App\Repositories\Category;

use App\Interfaces\Category\CategoryInterface;
use App\Models\InventoryCategory;
use App\Repositories\BaseRepository;

class CategoryRepository extends BaseRepository implements CategoryInterface
{

    public function __construct(InventoryCategory $model)
    {
        parent::__construct($model);
    }

}
