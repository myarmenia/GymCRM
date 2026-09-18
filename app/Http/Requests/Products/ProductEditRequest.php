<?php

namespace App\Http\Requests\Products;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

            'name' => ['required', 'array', 'min:1'],
            'name.*' => ['required', 'string', 'max:255'],

            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string'],
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('inventory_products', 'sku')->ignore($this->route('id')),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('inventory_products', 'barcode')->ignore($this->route('id')),
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

    public function messages(): array
    {
        return [
            'sku.required' => __('backend_messages.sku_field_required'),
            'sku.unique' => __('backend_messages.product_with_this_sku_already_exists'),
            'barcode.unique' => __('backend_messages.product_with_this_barcode_already_exists'),
        ];
    }
}
