<?php

namespace App\Http\Requests\MembershipSales;

use App\Models\PaymentMethod;
use App\Models\User;
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
            'reminder_scheduled_at',
            'reminder_title',
            'reminder_description',
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

        if ($paymentMethod && ! $paymentMethod->cardTypes->count()) {
            $data['card_type_id'] = null;
        }

        if (! empty($data)) {
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
            'stay_debt' => ['sometimes', 'boolean'],
            'is_partial_payment' => ['sometimes', 'boolean'],
            'is_full_payment' => ['sometimes', 'boolean'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'card_type_id' => ['nullable', 'integer', 'exists:card_types,id'],
            'payment_type' => ['nullable', Rule::in(['payment', 'refund'])],
            'payment_record_status' => ['nullable', Rule::in(['unpaid', 'pending', 'paid', 'cancelled'])],
            'payment_notes' => ['nullable', 'string'],
            'reminder_scheduled_at' => [
                'nullable',
                'date',
                'after:now',
            ],
            'reminder_recipient_ids' => [
                'nullable',
                'array',
                'min:1',
            ],
            'reminder_recipient_ids.*' => ['integer', 'exists:users,id'],
            'reminder_title' => ['nullable', 'string', 'max:255'],
            'reminder_description' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_partial_payment') && $this->boolean('is_full_payment')) {
                $validator->errors()->add('is_full_payment', __('backend.membership_sales.select_partial_or_full_payment'));
            }

            if (! $this->boolean('stay_debt')) {
                if ($this->submittedPaymentAmount() > 0 && ! $this->filled('payment_method_id')) {
                    $validator->errors()->add('payment_method_id', __('backend.membership_sales.payment_method_required'));
                }

                $paymentMethod = $this->filled('payment_method_id')
                    ? PaymentMethod::query()->with('cardTypes')->find($this->input('payment_method_id'))
                    : null;

                if ($paymentMethod) {
                    $requiresCardType = $paymentMethod->cardTypes->count() > 0;

                    if ($requiresCardType && ! $this->filled('card_type_id')) {
                        $validator->errors()->add('card_type_id', __('backend.membership_sales.card_type_required'));
                    }

                    if ($this->filled('card_type_id') && ! $paymentMethod->cardTypes->contains('id', (int) $this->input('card_type_id'))) {
                        $validator->errors()->add('card_type_id', __('backend.membership_sales.card_type_mismatch'));
                    }
                }
            }

            $actor = $this->user();
            $recipientIds = array_values(array_unique(array_map(
                'intval',
                (array) $this->input('reminder_recipient_ids', [])
            )));

            if ($actor && ! empty($recipientIds)) {
                $hasInvalidRecipient = User::query()
                    ->whereIn('id', $recipientIds)
                    ->where(function ($query) use ($actor) {
                        $query
                            ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'owner'))
                            ->when(! $actor->hasRole('owner'), fn ($userQuery) => $userQuery->orWhere('gym_id', '!=', $actor->gym_id));
                    })
                    ->exists();

                if ($hasInvalidRecipient) {
                    $validator->errors()->add('reminder_recipient_ids', __('backend.membership_sales.reminder_recipient_unavailable'));
                }
            }

            if (! $this->boolean('apply_discount')) {
                return;
            }

            if (! $this->filled('discount_type')) {
                $validator->errors()->add('discount_type', __('backend.membership_sales.discount_type_required'));
            }

            if (! $this->filled('discount_value')) {
                $validator->errors()->add('discount_value', __('backend.membership_sales.discount_value_required'));
            }

            if ($this->input('discount_type') === 'percent' && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', __('backend.membership_sales.percentage_discount_max'));
            }

        });
    }

    public function messages(): array
    {
        return [
            'required' => __('validation.required'),
            'integer' => __('validation.integer'),
            'numeric' => __('validation.numeric'),
            'min.numeric' => __('validation.min.numeric'),
            'array' => __('validation.array'),
            'boolean' => __('validation.boolean'),
            'date' => __('validation.date'),
            'after_or_equal' => __('validation.after_or_equal'),
            'exists' => __('validation.exists'),
            'in' => __('validation.exists'),
            'string' => __('validation.string'),
        ];
    }

    public function attributes(): array
    {
        return [
            'person_id' => __('backend.attributes.person'),
            'membership_plan_id' => __('backend.attributes.membership_plan'),
            'start_date' => __('backend.attributes.start'),
            'end_date' => __('backend.attributes.end'),
            'membership_discount_ids' => __('backend.attributes.membership_discounts'),
            'membership_discount_ids.*' => __('backend.attributes.membership_discount'),
            'discount_type' => __('backend.attributes.discount_type'),
            'discount_value' => __('backend.attributes.discount_value'),
            'is_hdm' => __('backend.attributes.cash_register'),
            'notes' => __('backend.attributes.notes'),
            'trainer_id' => __('backend.attributes.trainer'),
            'stay_debt' => __('backend.attributes.stay_debt'),
            'amount' => __('backend.attributes.payment_amount'),
            'payment_amount' => __('backend.attributes.payment_amount'),
            'payment_method_id' => __('backend.attributes.payment_method'),
            'card_type_id' => __('backend.attributes.card_type'),
            'payment_type' => __('backend.attributes.payment_type'),
            'payment_record_status' => __('backend.attributes.payment_status'),
            'payment_notes' => __('backend.attributes.payment_notes'),
            'reminder_scheduled_at' => __('backend.attributes.reminder_scheduled_at'),
            'reminder_recipient_ids' => __('backend.attributes.reminder_recipients'),
            'reminder_title' => __('backend.attributes.reminder_title'),
            'reminder_description' => __('backend.attributes.reminder_description'),
        ];
    }

    protected function submittedPaymentAmount(): float
    {
        if ($this->boolean('stay_debt')) {
            return 0;
        }

        if ($this->boolean('is_full_payment')) {
            return (float) ($this->input('payment_amount') ?? $this->input('amount') ?? 0);
        }

        return (float) ($this->input('payment_amount') ?? $this->input('amount') ?? 0);
    }

    protected function discountTypes(): array
    {
        return ['percent'];
    }
}
