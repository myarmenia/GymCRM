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
            'required' => __('backend_messages.attribute_field_required'),
            'integer' => __('backend_messages.attribute_must_be_integer'),
            'numeric' => __('backend_messages.attribute_must_be_number'),
            'min.numeric' => __('backend_messages.attribute_must_be_least_min'),
            'boolean' => __('backend_messages.attribute_field_must_be_true_or_false'),
            'exists' => __('backend_messages.selected_attribute_invalid'),
            'in' => __('backend_messages.selected_attribute_invalid'),
            'string' => __('backend_messages.attribute_must_be_string'),
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_sale_id' => __('backend_messages.membership_sale_lowercase'),
            'amount' => __('backend_messages.amount_lowercase'),
            'payment_method_id' => __('backend_messages.payment_method_lowercase'),
            'card_type_id' => __('backend_messages.card_type'),
            'status' => __('backend_messages.status_lowercase'),
            'type' => __('backend_messages.payment_type'),
            'is_hdm' => __('backend_messages.fiscal_receipt'),
            'notes' => __('backend_messages.notes'),
        ];
    }
}
