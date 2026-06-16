<?php

namespace App\Repositories\SubCategoryTranslations;

use App\Interfaces\ServiceTypeTranslations\ServiceTypeTranslationRepositoryInterface;
use App\Interfaces\SubCategoryTranslations\SubCategoryTranslationInterface;
use App\Interfaces\SubCategoryTranslations\SubCategoryTranslationRepositoryInterface;
use App\Models\InventoryCategoryTranslation;
use App\Repositories\BaseRepository;

class SubCategoryTranslationsRepository extends BaseRepository implements SubCategoryTranslationInterface
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