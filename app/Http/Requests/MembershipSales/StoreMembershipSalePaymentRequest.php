<?php

namespace App\Http\Requests\MembershipSales;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'payment_method_id',
            'card_type_id',
            'payment_notes',
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

        $paymentMethod = $this->input('payment_method_id')
            ? PaymentMethod::query()->with('cardTypes')->find($this->input('payment_method_id'))
            : null;

        if ($paymentMethod && !$paymentMethod->cardTypes->count()) {
            $data['card_type_id'] = null;
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'is_partial_payment' => ['sometimes', 'boolean'],
            'is_full_payment' => ['sometimes', 'boolean'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'card_type_id' => ['nullable', 'integer', 'exists:card_types,id'],
            'payment_notes' => ['nullable', 'string'],
            'is_hdm' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_partial_payment') && $this->boolean('is_full_payment')) {
                $validator->errors()->add('is_full_payment', __('backend.membership_sales.select_partial_or_full_payment'));
            }

            if (!$this->boolean('is_partial_payment') && !$this->boolean('is_full_payment')) {
                $validator->errors()->add('is_full_payment', __('backend.membership_sales.select_payment_type'));
            }

            if ($this->submittedPaymentAmount() > 0 && !$this->filled('payment_method_id')) {
                $validator->errors()->add('payment_method_id', __('backend.membership_sales.payment_method_required'));
            }

            $paymentMethod = $this->filled('payment_method_id')
                ? PaymentMethod::query()->with('cardTypes')->find($this->input('payment_method_id'))
                : null;

            if (!$paymentMethod) {
                return;
            }

            if ($paymentMethod->cardTypes->count() && !$this->filled('card_type_id')) {
                $validator->errors()->add('card_type_id', __('backend.membership_sales.card_type_required'));
            }

            if ($this->filled('card_type_id') && !$paymentMethod->cardTypes->contains('id', (int) $this->input('card_type_id'))) {
                $validator->errors()->add('card_type_id', __('backend.membership_sales.card_type_mismatch'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'integer' => __('validation.integer'),
            'numeric' => __('validation.numeric'),
            'min.numeric' => __('validation.min.numeric'),
            'boolean' => __('validation.boolean'),
            'exists' => __('validation.exists'),
            'string' => __('validation.string'),
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => __('backend.attributes.payment_amount'),
            'payment_amount' => __('backend.attributes.payment_amount'),
            'payment_method_id' => __('backend.attributes.payment_method'),
            'card_type_id' => __('backend.attributes.card_type'),
            'payment_notes' => __('backend.attributes.payment_notes'),
            'is_hdm' => __('backend.attributes.cash_register'),
        ];
    }

    protected function submittedPaymentAmount(): float
    {
        return (float) ($this->input('payment_amount') ?? $this->input('amount') ?? 0);
    }
}
