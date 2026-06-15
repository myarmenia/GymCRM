<?php

namespace App\Http\Requests\MembershipPlans;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class UpdateMembershipPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_category_id' => ['required', 'exists:membership_categories,id'],

            'price' => ['required', 'numeric', 'min:0'],
            'price_type' => ['required', Rule::in(['fixed', 'percent'])],
            'price_value' => ['required', 'numeric', 'min:0'],

            'duration_type' => ['required', 'in:day,month,year,visit,period'],
            'duration_value' => ['nullable', 'integer', 'min:1'],
            'visits_limit' => ['nullable', 'integer', 'min:1'],

            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            'guest_limit' => ['nullable', 'integer', 'min:0'],
            'freeze_limit' => ['nullable', 'integer', 'min:0'],

            'active' => ['boolean'],

            'translations' => ['required', 'array'],
            'translations.hy.name' => ['required', 'string', 'max:255'],
            'translations.hy.description' => ['nullable', 'string'],

            'schedule_name_id' => ['nullable', 'exists:schedule_names,id'],

            'trainers' => ['nullable', 'array'],
            'trainers.*.trainer_id' => ['required', 'exists:users,id'],
            'trainers.*.price_type' => ['required', 'in:fixed,percent'],
            'trainers.*.price_value' => ['required', 'numeric', 'min:0'],
            'trainers.*.total_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
