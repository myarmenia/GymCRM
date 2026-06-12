<?php

namespace App\Http\Requests\MembershipSales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembershipSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('discount_type') && $this->input('discount_type') === '') {
            $data['discount_type'] = null;
        }

        if ($this->has('discount_value') && $this->input('discount_value') === '') {
            $data['discount_value'] = null;
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'membership_discount_ids' => ['nullable', 'array'],
            'membership_discount_ids.*' => ['integer', 'exists:discounts,id'],
            'apply_discount' => ['sometimes', 'boolean'],
            'discount_type' => ['nullable', Rule::in($this->discountTypes())],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->boolean('apply_discount')) {
                return;
            }

            if (!$this->filled('discount_type')) {
                $validator->errors()->add('discount_type', __('Discount type is required.'));
            }

            if (!$this->filled('discount_value')) {
                $validator->errors()->add('discount_value', __('Discount value is required.'));
            }

            if ($this->input('discount_type') === 'percent' && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', __('Percentage discount cannot be greater than 100.'));
            }
        });
    }

    protected function discountTypes(): array
    {
        $migration = file_get_contents(
            base_path('database/migrations/2026_06_08_000004_create_membership_sales_table.php')
        );

        if (!preg_match("/enum\\('discount_type',\\s*\\[(.*?)\\]\)/s", $migration, $matches)) {
            return [];
        }

        preg_match_all("/'([^']+)'/", $matches[1], $values);

        return $values[1] ?? [];
    }
}
