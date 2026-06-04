<?php

namespace App\Interfaces\ProductTranslations;

interface ProductTranslationInterface
{
    public function updateTranslation(array $conditions, array $data);
}
