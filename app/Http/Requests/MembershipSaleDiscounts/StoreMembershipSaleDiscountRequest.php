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
}
