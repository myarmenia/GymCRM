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
            'required' => __('backend_messages.attribute_field_required'),
            'integer' => __('backend_messages.attribute_must_be_integer'),
            'numeric' => __('backend_messages.attribute_must_be_number'),
            'min.numeric' => __('backend_messages.attribute_must_be_least_min'),
            'exists' => __('backend_messages.selected_attribute_invalid'),
            'in' => __('backend_messages.selected_attribute_invalid'),
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_sale_id' => __('backend_messages.membership_sale_lowercase'),
            'discount_id' => __('backend_messages.discount'),
            'discount_type' => __('backend_messages.discount_type'),
            'discount_value' => __('backend_messages.discount_value'),
            'discount_amount' => __('backend_messages.discount_amount'),
        ];
    }
}
