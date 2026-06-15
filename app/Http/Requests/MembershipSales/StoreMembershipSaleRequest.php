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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_partial_payment') && $this->boolean('is_full_payment')) {
                $validator->errors()->add('is_full_payment', 'Ընտրեք կամ մասնակի, կամ ամբողջական վճարում։');
            }

            if (!$this->boolean('stay_debt')) {
                if ($this->submittedPaymentAmount() > 0 && !$this->filled('payment_method_id')) {
                    $validator->errors()->add('payment_method_id', 'Վճարման եղանակը պարտադիր է, եթե վճարվող գումարը մեծ է 0-ից։');
                }

                $paymentMethod = $this->filled('payment_method_id')
                    ? PaymentMethod::query()->with('cardTypes')->find($this->input('payment_method_id'))
                    : null;

                if ($paymentMethod) {
                    $requiresCardType = $paymentMethod->cardTypes->count() > 0;

                    if ($requiresCardType && !$this->filled('card_type_id')) {
                        $validator->errors()->add('card_type_id', 'Այս վճարման եղանակի համար քարտի տեսակը պարտադիր է։');
                    }

                    if ($this->filled('card_type_id') && !$paymentMethod->cardTypes->contains('id', (int) $this->input('card_type_id'))) {
                        $validator->errors()->add('card_type_id', 'Ընտրված քարտի տեսակը չի համապատասխանում վճարման եղանակին։');
                    }
                }
            }

            if (!$this->boolean('apply_discount')) {
                return;
            }

            if (!$this->filled('discount_type')) {
                $validator->errors()->add('discount_type', 'Զեղչի տեսակը պարտադիր է։');
            }

            if (!$this->filled('discount_value')) {
                $validator->errors()->add('discount_value', 'Զեղչի արժեքը պարտադիր է։');
            }

            if ($this->input('discount_type') === 'percent' && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'Տոկոսային զեղչը չի կարող լինել 100-ից մեծ։');
            }

        });
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute դաշտը պարտադիր է։',
            'integer' => ':attribute դաշտը պետք է լինի ամբողջ թիվ։',
            'numeric' => ':attribute դաշտը պետք է լինի թիվ։',
            'min.numeric' => ':attribute դաշտը պետք է լինի առնվազն :min։',
            'array' => ':attribute դաշտը պետք է լինի ցուցակ։',
            'boolean' => ':attribute դաշտը պետք է լինի այո կամ ոչ։',
            'date' => ':attribute դաշտը պետք է լինի վավեր ամսաթիվ։',
            'after_or_equal' => ':attribute-ը պետք է լինի :date-ից ոչ շուտ։',
            'exists' => 'Ընտրված :attribute-ը անվավեր է։',
            'in' => 'Ընտրված :attribute-ը անվավեր է։',
            'string' => ':attribute դաշտը պետք է լինի տեքստ։',
        ];
    }

    public function attributes(): array
    {
        return [
            'person_id' => 'հաճախորդ',
            'membership_plan_id' => 'աբոնեմենտ',
            'start_date' => 'սկիզբ',
            'end_date' => 'ավարտ',
            'membership_discount_ids' => 'աբոնեմենտի զեղչեր',
            'membership_discount_ids.*' => 'աբոնեմենտի զեղչ',
            'discount_type' => 'զեղչի տեսակ',
            'discount_value' => 'զեղչի արժեք',
            'is_hdm' => 'ՀԴՄ',
            'notes' => 'նշումներ',
            'trainer_id' => 'մարզիչ',
            'stay_debt' => 'մնալ պարտք',
            'amount' => 'վճարվող գումար',
            'payment_amount' => 'վճարվող գումար',
            'payment_method_id' => 'վճարման եղանակ',
            'card_type_id' => 'քարտի տեսակ',
            'payment_type' => 'վճարման տեսակ',
            'payment_record_status' => 'վճարման կարգավիճակ',
            'payment_notes' => 'վճարման նշումներ',
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
