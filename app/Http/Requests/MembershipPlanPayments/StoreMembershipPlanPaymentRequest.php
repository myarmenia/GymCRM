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
            'notes' => ['nullable', 'string'],
        ];
    }
}
