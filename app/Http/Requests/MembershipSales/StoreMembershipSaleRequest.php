<?php

namespace App\Http\Requests\MembershipSales;

use App\Models\PaymentMethod;
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
        if ($this->route('person')) {
            $this->merge([
                'person_id' => $this->route('person'),
            ]);
        }

        $nullableFields = [
            'end_date',
            'trainer_id',
            'payment_method_id',
            'card_type_id',
            'payment_type',
            'payment_record_status',
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
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'membership_discount_ids' => ['nullable', 'array'],
            'membership_discount_ids.*' => ['integer', 'exists:discounts,id'],
            'apply_discount' => ['sometimes', 'boolean'],
            'discount_type' => ['nullable', Rule::in($this->discountTypes())],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'is_hdm' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_partial_payment' => ['sometimes', 'boolean'],
            'is_full_payment' => ['sometimes', 'boolean'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'card_type_id' => ['nullable', 'integer', 'exists:card_types,id'],
            'payment_type' => ['nullable', Rule::in(['payment', 'refund'])],
            'payment_record_status' => ['nullable', Rule::in(['unpaid', 'pending', 'paid', 'cancelled'])],
            'payment_notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_partial_payment') && $this->boolean('is_full_payment')) {
                $validator->errors()->add('is_full_payment', __('Choose either partial payment or full payment.'));
            }

            if ($this->submittedPaymentAmount() > 0 && !$this->filled('payment_method_id')) {
                $validator->errors()->add('payment_method_id', __('Payment method is required when payment amount is greater than zero.'));
            }

            $paymentMethod = $this->filled('payment_method_id')
                ? PaymentMethod::query()->with('cardTypes')->find($this->input('payment_method_id'))
                : null;

            if ($paymentMethod) {
                $requiresCardType = $paymentMethod->cardTypes->count() > 0;

                if ($requiresCardType && !$this->filled('card_type_id')) {
                    $validator->errors()->add('card_type_id', __('Card type is required for this payment method.'));
                }

                if ($this->filled('card_type_id') && !$paymentMethod->cardTypes->contains('id', (int) $this->input('card_type_id'))) {
                    $validator->errors()->add('card_type_id', __('Selected card type does not belong to the selected payment method.'));
                }
            }

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

    protected function submittedPaymentAmount(): float
    {
        if ($this->boolean('is_full_payment')) {
            return (float) ($this->input('payment_amount') ?? $this->input('amount') ?? 0);
        }

        return (float) ($this->input('payment_amount') ?? $this->input('amount') ?? 0);
    }

    protected function discountTypes(): array
    {
        $migration = file_get_contents(
            base_path('database/migrations/2026_06_08_000004_create_membership_sales_table.php')
        );

        if (!preg_match("/enum\\('discount_type',\\s*\\[(.*?)\\]\\)/s", $migration, $matches)) {
            return [];
        }

        preg_match_all("/'([^']+)'/", $matches[1], $values);

        return $values[1] ?? [];
    }
}
