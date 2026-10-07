<?php

namespace App\Http\Requests\OwnerSales;

class UpdateOwnerSaleRequest extends StoreOwnerSaleRequest
{
    public function rules(): array
    {
        return parent::rules();
    }
}
