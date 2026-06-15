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
            'status' => ['required', Rule::in(['waiting', 'active', 'frozen', 'expired', 'deleted'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'visits_used' => ['nullable', 'integer', 'min:0'],
            'visits_left' => ['nullable', 'integer', 'min:0'],
            'freeze_used' => ['nullable', 'integer', 'min:0'],
            'guest_used' => ['nullable', 'integer', 'min:0'],
            'next_membership_id' => ['nullable', 'integer', 'exists:person_memberships,id'],
            'activated_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date'],
        ];
    }
}
