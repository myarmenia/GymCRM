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
                $validator->errors()->add('is_full_payment', 'Ընտրեք կամ մասնակի, կամ ամբողջական վճարում։');
            }

            if (!$this->boolean('is_partial_payment') && !$this->boolean('is_full_payment')) {
                $validator->errors()->add('is_full_payment', 'Ընտրեք վճարման տեսակը։');
            }

            if ($this->submittedPaymentAmount() > 0 && !$this->filled('payment_method_id')) {
                $validator->errors()->add('payment_method_id', 'Վճարման եղանակը պարտադիր է, եթե վճարվող գումարը մեծ է 0-ից։');
            }

            $paymentMethod = $this->filled('payment_method_id')
                ? PaymentMethod::query()->with('cardTypes')->find($this->input('payment_method_id'))
                : null;

            if (!$paymentMethod) {
                return;
            }

            if ($paymentMethod->cardTypes->count() && !$this->filled('card_type_id')) {
                $validator->errors()->add('card_type_id', 'Այս վճարման եղանակի համար քարտի տեսակը պարտադիր է։');
            }

            if ($this->filled('card_type_id') && !$paymentMethod->cardTypes->contains('id', (int) $this->input('card_type_id'))) {
                $validator->errors()->add('card_type_id', 'Ընտրված քարտի տեսակը չի համապատասխանում վճարման եղանակին։');
            }
        });
    }

    public function messages(): array
    {
        return [
            'integer' => ':attribute դաշտը պետք է լինի ամբողջ թիվ։',
            'numeric' => ':attribute դաշտը պետք է լինի թիվ։',
            'min.numeric' => ':attribute դաշտը պետք է լինի առնվազն :min։',
            'boolean' => ':attribute դաշտը պետք է լինի այո կամ ոչ։',
            'exists' => 'Ընտրված :attribute-ը անվավեր է։',
            'string' => ':attribute դաշտը պետք է լինի տեքստ։',
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'վճարվող գումար',
            'payment_amount' => 'վճարվող գումար',
            'payment_method_id' => 'վճարման եղանակ',
            'card_type_id' => 'քարտի տեսակ',
            'payment_notes' => 'վճարման նշումներ',
            'is_hdm' => 'ՀԴՄ',
        ];
    }

    protected function submittedPaymentAmount(): float
    {
        return (float) ($this->input('payment_amount') ?? $this->input('amount') ?? 0);
    }
}
