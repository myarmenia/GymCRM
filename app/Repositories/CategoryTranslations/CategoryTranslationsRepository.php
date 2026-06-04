<?php

namespace App\Repositories\CategoryTranslations;

use App\Interfaces\CategoryTranslations\CategoryTranslationInterface;
use App\Models\InventoryCategoryTranslation;
use App\Repositories\BaseRepository;

class CategoryTranslationsRepository extends BaseRepository implements CategoryTranslationInterface
{
    public function __construct(InventoryCategoryTranslation $model)
    {
        parent::__construct($model);
    }

    public function updateTranslation(array $conditions, array $data): ?InventoryCategoryTranslation
    {
        $translation = $this->query()
            ->where($conditions)
            ->first();

        if (!$translation) {
            return null;
        }

        $translation->update($data);

        return $translation;
    }
}
