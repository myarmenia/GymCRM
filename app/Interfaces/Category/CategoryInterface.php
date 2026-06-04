<?php

namespace App\Interfaces\Category;

use App\Interfaces\BaseInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;



interface CategoryInterface extends BaseInterface
{

    public function wherePaginateCategory(array $conditions, array $with = [], int $perPage = 10): LengthAwarePaginator;
    public function getParentCategories(string $locale): Collection;
    public function createCategory(array $data): Model;
}
