<?php

namespace App\Http\Requests\MembershipPlanPayments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipPlanPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_sale_id' => ['required', 'integer', 'exists:membership_sales,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'card_type_id' => ['nullable', 'integer', 'exists:card_types,id'],
            'status' => ['required', Rule::in(['unpaid', 'pending', 'paid', 'cancelled'])],
            'type' => ['required', Rule::in(['payment', 'refund'])],
            'is_hdm' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute դաշտը պարտադիր է։',
            'integer' => ':attribute դաշտը պետք է լինի ամբողջ թիվ։',
            'numeric' => ':attribute դաշտը պետք է լինի թիվ։',
            'min.numeric' => ':attribute դաշտը պետք է լինի առնվազն :min։',
            'boolean' => ':attribute դաշտը պետք է լինի այո կամ ոչ։',
            'exists' => 'Ընտրված :attribute-ը անվավեր է։',
            'in' => 'Ընտրված :attribute-ը անվավեր է։',
            'string' => ':attribute դաշտը պետք է լինի տեքստ։',
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_sale_id' => 'աբոնեմենտի վաճառք',
            'amount' => 'գումար',
            'payment_method_id' => 'վճարման եղանակ',
            'card_type_id' => 'քարտի տեսակ',
            'status' => 'կարգավիճակ',
            'type' => 'վճարման տեսակ',
            'is_hdm' => 'ՀԴՄ',
            'notes' => 'նշումներ',
        ];
    }
}
