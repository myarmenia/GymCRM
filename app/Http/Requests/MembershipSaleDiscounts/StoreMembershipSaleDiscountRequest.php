<?php

namespace App\Http\Requests\MembershipSaleDiscounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipSaleDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_sale_id' => ['required', 'integer', 'exists:membership_sales,id'],
            'discount_id' => ['required', 'integer', 'exists:discounts,id'],
            'discount_type' => ['required', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute դաշտը պարտադիր է։',
            'integer' => ':attribute դաշտը պետք է լինի ամբողջ թիվ։',
            'numeric' => ':attribute դաշտը պետք է լինի թիվ։',
            'min.numeric' => ':attribute դաշտը պետք է լինի առնվազն :min։',
            'exists' => 'Ընտրված :attribute-ը անվավեր է։',
            'in' => 'Ընտրված :attribute-ը անվավեր է։',
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_sale_id' => 'աբոնեմենտի վաճառք',
            'discount_id' => 'զեղչ',
            'discount_type' => 'զեղչի տեսակ',
            'discount_value' => 'զեղչի արժեք',
            'discount_amount' => 'զեղչի գումար',
        ];
    }
}
