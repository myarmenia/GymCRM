<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductEditRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            'category_id' => ['required', 'exists:inventory_categories,id'],
            'sub_category_id' => ['required', 'exists:inventory_categories,id'],
            'measurement_unit_id' => ['required', 'exists:measurement_units,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],

            'name.hy' => ['required', 'string', 'max:255'],
            //'name.ru' => ['required', 'string', 'max:255'],
            //'name.en' => ['required', 'string', 'max:255'],

            'description.hy' => ['nullable', 'string'],
            //'description.ru' => ['nullable', 'string'],
            //'description.en' => ['nullable', 'string'],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                //'unique:inventory_products,sku',
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                //'unique:inventory_products,barcode',
            ],
            'default_purchase_price' => [
                'required',
                'numeric',
                'min:0',
                'lt:default_sale_price',
            ],
            'default_sale_price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'min_stock_alert' => [
                'required',
                'integer',
                'min:0',
                'regex:/^[0-9]+$/',
                'lt:quantity',
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            'status' => ['required', 'boolean'],

            'quantity' => [
                'required',
                'integer',
                'min:0',
                'regex:/^[0-9]+$/'
            ],
            'reserved_quantity' => ['nullable', 'integer', 'min:0', 'regex:/^[0-9]+$/'],
            'average_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
