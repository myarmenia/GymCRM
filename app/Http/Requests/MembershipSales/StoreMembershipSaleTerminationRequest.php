<?php

namespace App\Http\Requests\MembershipSales;

use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipSaleTerminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refund_amount' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'refund_amount.required' => 'Վերադարձի գումարը պարտադիր է։',
            'refund_amount.numeric' => 'Վերադարձի գումարը պետք է լինի թիվ։',
            'refund_amount.gt' => 'Վերադարձի գումարը պետք է լինի 0-ից մեծ։',
        ];
    }
}
