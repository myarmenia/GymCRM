<?php

namespace App\Http\Requests\ProductConsumption;

use Illuminate\Foundation\Http\FormRequest;

class ProductConsumptionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array', 'min:1'],

            'products.*.id' => [
                'required',
                'integer',
                'exists:inventory_products,id',
            ],

            'products.*.quantity' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'products.*.available_quantity' => [
                'required',
                'numeric',
                'min:0',
            ],

            'products.*.description' => [
                'nullable',
                'string',
            ],
            'products.*.purchase_price' => ['required', 'numeric', 'min:0'],
            'products.*.sale_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'products.*.quantity.required' => 'Consumption quantity is required.',
            'products.*.quantity.gt' => 'Consumption quantity must be greater than 0.',
            'products.*.id.exists' => 'Selected product does not exist.',
        ];
    }
}
