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
            'refund_amount.required' => __('backend.membership_sales.refund_amount_required'),
            'refund_amount.numeric' => __('backend.membership_sales.refund_amount_numeric'),
            'refund_amount.gt' => __('backend.membership_sales.refund_amount_positive'),
        ];
    }
}
