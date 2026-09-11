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
                $validator->errors()->add('is_full_refund', 'Ընտրեք կամ մասնակի, կամ ամբողջական վերադարձ։');
            }

            if (! $this->boolean('is_partial_refund') && ! $this->boolean('is_full_refund')) {
                $validator->errors()->add('is_full_refund', 'Ընտրեք վերադարձի տեսակը։');
            }

        });
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Վերադարձի գումարը պարտադիր է։',
            'amount.numeric' => 'Վերադարձի գումարը պետք է լինի թիվ։',
            'amount.gt' => 'Վերադարձի գումարը պետք է լինի 0-ից մեծ։',
            'parent_payment_id.required' => 'Ընտրեք վերադարձվող վճարումը։',
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
            'parent_payment_id' => 'վերադարձվող վճարում',
            'refund_notes' => 'վերադարձի նշումներ',
        ];
    }
}
