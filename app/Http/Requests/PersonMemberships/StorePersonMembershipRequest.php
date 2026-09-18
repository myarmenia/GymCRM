<?php

namespace App\Http\Requests\PersonMemberships;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_sale_id' => ['required', 'integer', 'exists:membership_sales,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(['waiting', 'active', 'frozen', 'expired', 'deleted', 'cancelled'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'valid_at' => ['nullable', 'date', 'after_or_equal:start_date'],
            'visits_used' => ['nullable', 'integer', 'min:0'],
            'visits_left' => ['nullable', 'integer', 'min:0'],
            'freeze_used' => ['nullable', 'integer', 'min:0'],
            'guest_used' => ['nullable', 'integer', 'min:0'],
            'freeze_left' => ['nullable', 'integer', 'min:0'],
            'guest_left' => ['nullable', 'integer', 'min:0'],
            'next_membership_id' => ['nullable', 'integer', 'exists:person_memberships,id'],
            'activated_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('backend_messages.attribute_field_required'),
            'integer' => __('backend_messages.attribute_must_be_integer'),
            'numeric' => __('backend_messages.attribute_must_be_number'),
            'min.numeric' => __('backend_messages.attribute_must_be_least_min'),
            'date' => __('backend_messages.attribute_must_be_valid_date'),
            'after_or_equal' => __('backend_messages.attribute_must_be_date_after_or_equal_date'),
            'exists' => __('backend_messages.selected_attribute_invalid'),
            'in' => __('backend_messages.selected_attribute_invalid'),
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_sale_id' => __('backend_messages.membership_sale_lowercase'),
            'user_id' => __('backend_messages.user_lowercase'),
            'person_id' => __('backend_messages.customer'),
            'gym_id' => __('backend_messages.gym_lowercase'),
            'membership_plan_id' => __('backend_messages.membership_lowercase'),
            'trainer_id' => __('backend_messages.trainer_lowercase'),
            'status' => __('backend_messages.status_lowercase'),
            'start_date' => __('backend_messages.start_date'),
            'end_date' => __('backend_messages.end_date'),
            'valid_at' => __('backend_messages.valid_until'),
            'visits_used' => __('backend_messages.used_visits'),
            'visits_left' => __('backend_messages.remaining_visits'),
            'freeze_used' => __('backend_messages.number_freezes'),
            'guest_used' => __('backend_messages.number_guests'),
            'freeze_left' => __('backend_messages.remaining_freezes'),
            'guest_left' => __('backend_messages.remaining_guests'),
            'next_membership_id' => __('backend_messages.next_membership'),
            'activated_at' => __('backend_messages.activation_date'),
            'expired_at' => __('backend_messages.end_date_label'),
        ];
    }
}
