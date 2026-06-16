<?php

namespace App\Http\Requests\MembershipSales;

use App\Models\PaymentMethod;
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
            'payment_method_id',
            'card_type_id',
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
            'is_partial_refund' => ['sometimes', 'boolean'],
            'is_full_refund' => ['sometimes', 'boolean'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'card_type_id' => ['nullable', 'integer', 'exists:card_types,id'],
            'refund_notes' => ['nullable', 'string'],
            'is_hdm' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->boolean('is_partial_refund') && $this->boolean('is_full_refund')) {
                $validator->errors()->add('is_full_refund', 'Ընտրեք կամ մասնակի, կամ ամբողջական վերադարձ։');
            }

            if (!$this->boolean('is_partial_refund') && !$this->boolean('is_full_refund')) {
                $validator->errors()->add('is_full_refund', 'Ընտրեք վերադարձի տեսակը։');
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
            'amount.required' => 'Վերադարձի գումարը պարտադիր է։',
            'amount.numeric' => 'Վերադարձի գումարը պետք է լինի թիվ։',
            'amount.gt' => 'Վերադարձի գումարը պետք է լինի 0-ից մեծ։',
            'payment_method_id.required' => 'Վերադարձի վճարման եղանակը պարտադիր է։',
            'integer' => ':attribute դաշտը պետք է լինի ամբողջ թիվ։',
            'boolean' => ':attribute դաշտը պետք է լինի այո կամ ոչ։',
            'exists' => 'Ընտրված :attribute-ը անվավեր է։',
            'string' => ':attribute դաշտը պետք է լինի տեքստ։',
        ];
    }

    public function attributes(): array
    {
        return [
            'amount' => 'վերադարձի գումար',
            'payment_method_id' => 'վերադարձի վճարման եղանակ',
            'card_type_id' => 'քարտի տեսակ',
            'refund_notes' => 'վերադարձի նշումներ',
            'is_hdm' => 'ՀԴՄ',
        ];
    }
}
