<?php

namespace App\Http\Requests\MembershipSales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'end_date',
            'discount_id',
            'trainer_id',
            'payment_method_id',
            'card_type_id',
            'payment_notes',
            'notes',
        ];

        $data = [];

        foreach ($nullableFields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $data[$field] = null;
            }
        }

        if ($this->has('amount') && $this->input('amount') === '') {
            $data['amount'] = 0;
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'discount_id' => ['nullable', 'integer', 'exists:discounts,id'],
            'is_hdm' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'price_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'card_type_id' => ['nullable', 'integer', 'exists:card_types,id'],
            'payment_notes' => ['nullable', 'string'],
        ];
    }
}
