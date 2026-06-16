<?php

namespace App\Repositories\ProductTranslations;

use App\Interfaces\ProductTranslations\ProductTranslationInterface;
use App\Models\InventoryProductTranslation;
use App\Repositories\BaseRepository;

class ProductTranslationRepository extends BaseRepository implements ProductTranslationInterface
{
    public function __construct(InventoryProductTranslation $model)
    {
        parent::__construct($model);
    }

    public function updateTranslation(array $conditions, array $data): ?InventoryProductTranslation
    {
        return $this->model->updateOrCreate($conditions, $data);
    }
}
