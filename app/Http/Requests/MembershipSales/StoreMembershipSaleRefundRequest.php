<?php

namespace App\Http\Requests\MembershipSales;

use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipSaleRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'refund_notes',
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

        if (! empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'is_partial_refund' => ['sometimes', 'boolean'],
            'is_full_refund' => ['sometimes', 'boolean'],
            'parent_payment_id' => ['required', 'integer', 'exists:membership_plan_payments,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'refund_notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_partial_refund') && $this->boolean('is_full_refund')) {
                $validator->errors()->add('is_full_refund', __('backend.membership_sales.select_partial_or_full_refund'));
            }

            if (! $this->boolean('is_partial_refund') && ! $this->boolean('is_full_refund')) {
                $validator->errors()->add('is_full_refund', __('backend.membership_sales.select_refund_type'));
            }

        });
    }

    public function messages(): array
    {
        return [
            'amount.required' => __('backend.membership_sales.refund_amount_required'),
            'amount.numeric' => __('backend.membership_sales.refund_amount_numeric'),
            'amount.gt' => __('backend.membership_sales.refund_amount_positive'),
            'parent_payment_id.required' => __('backend.membership_sales.select_refunded_payment'),
            'integer' => __('validation.integer'),
            'boolean' => __('validation.boolean'),
            'exists' => __('validation.exists'),
            'string' => __('validation.string'),
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => __('backend.attributes.refund_amount'),
            'parent_payment_id' => __('backend.attributes.refunded_payment'),
            'refund_notes' => __('backend.attributes.refund_notes'),
        ];
    }
}
