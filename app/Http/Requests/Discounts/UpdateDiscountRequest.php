<?php

namespace App\Http\Requests\Discounts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'boolean'],
            'translations' => ['required', 'array', 'min:1'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
            'membership_plan_ids' => ['nullable', 'array'],
            'membership_plan_ids.*' => [
                'integer',
                Rule::exists('membership_plans', 'id')->where(function ($query): void {
                    if ($this->user()?->gym_id !== null) {
                        $query->where('gym_id', $this->user()->gym_id);
                    }
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('backend_messages.attribute_field_required'),
            'numeric' => __('backend_messages.attribute_must_be_number'),
            'min.numeric' => __('backend_messages.attribute_must_be_least_min'),
            'array' => __('backend_messages.attribute_must_be_array'),
            'boolean' => __('backend_messages.attribute_field_must_be_true_or_false'),
            'date' => __('backend_messages.attribute_must_be_valid_date'),
            'after_or_equal' => __('backend_messages.attribute_must_be_date_after_or_equal_date'),
            'exists' => __('backend_messages.selected_attribute_invalid'),
            'in' => __('backend_messages.selected_attribute_invalid'),
            'string' => __('backend_messages.attribute_must_be_string'),
            'max.string' => __('backend_messages.attribute_may_not_be_greater_than_max_characters'),
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => __('backend_messages.discount_type'),
            'value' => __('backend_messages.discount_value'),
            'start_date' => __('backend_messages.start_date'),
            'end_date' => __('backend_messages.end_date'),
            'status' => __('backend_messages.status_lowercase'),
            'translations' => __('backend_messages.translations'),
            'translations.*.name' => __('backend_messages.first_name'),
            'translations.*.description' => __('backend_messages.description_lowercase'),
            'membership_plan_ids' => __('backend_messages.memberships'),
            'membership_plan_ids.*' => __('backend_messages.membership_lowercase'),
        ];
    }
}
