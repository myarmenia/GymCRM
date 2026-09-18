<?php

namespace App\Http\Requests\Memberships;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MembershipPlanStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_category_id' => [
                'required',
                'exists:membership_categories,id',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'price_type' => ['required', Rule::in(['percent'])],
            'price_value' => ['required', 'numeric', 'min:0', 'max:100'],

            'duration_type' => [
                'required',
                Rule::in(['day', 'month', 'year', 'visit', 'period']),
            ],

            'duration_value' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'visits_limit' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'start_date' => [
                'nullable',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'guest_limit' => [
                'required',
                'integer',
                'min:0',
            ],

            'freeze_limit' => [
                'required',
                'integer',
                'min:0',
            ],

            'active' => [
                'required',
                'boolean',
            ],

            'translations' => [
                'required',
                'array',
                'min:1',
            ],

            'translations.*.name' => [
                'required',
                'string',
                'max:255',
            ],

            'translations.*.description' => [
                'nullable',
                'string',
            ],
            'trainers' => [
                'nullable',
                'array',
            ],
            'trainers.*.trainer_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'trainers.*.price_type' => ['required', Rule::in(['percent'])],
            'trainers.*.price_value' => ['required', 'numeric', 'min:0', 'max:100'],
            'schedule_name_id' => [
                'required',
                'integer',
                'exists:schedule_names,id',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            switch ($this->duration_type) {

                case 'day':
                case 'month':
                case 'year':

                    if (! $this->duration_value) {
                        $validator->errors()->add(
                            'duration_value',
                            __('membership.duration_value_required')
                        );
                    }

                    break;

                case 'visit':

                    if (! $this->visits_limit) {
                        $validator->errors()->add(
                            'visits_limit',
                            __('membership.visits_limit_required')
                        );
                    }

                    if (! $this->duration_value) {
                        $validator->errors()->add(
                            'duration_value',
                            __('membership.membership_period_required')
                        );
                    }

                    break;

                case 'period':

                    if (! $this->start_date) {
                        $validator->errors()->add(
                            'start_date',
                            __('membership.start_date_required')
                        );
                    }

                    if (! $this->end_date) {
                        $validator->errors()->add(
                            'end_date',
                            __('membership.end_date_required')
                        );
                    }

                    break;
            }
        });
    }

    public function messages(): array
    {
        return [
            'required' => __('backend_messages.attribute_field_required'),
            'integer' => __('backend_messages.attribute_must_be_integer'),
            'numeric' => __('backend_messages.attribute_must_be_number'),
            'min.numeric' => __('backend_messages.attribute_must_be_least_min'),
            'min.integer' => __('backend_messages.attribute_must_be_least_min'),
            'array' => __('backend_messages.attribute_must_be_array'),
            'boolean' => __('backend_messages.attribute_field_must_be_true_or_false'),
            'date' => __('backend_messages.attribute_must_be_valid_date'),
            'after_or_equal' => __('backend_messages.attribute_must_be_date_after_or_equal_date'),
            'exists' => __('backend_messages.selected_attribute_invalid'),
            'in' => __('backend_messages.selected_attribute_invalid'),
            'string' => __('backend_messages.attribute_must_be_string'),
            'max.string' => __('backend_messages.attribute_may_not_be_greater_than_max_characters'),
            'max.numeric' => __('backend_messages.attribute_may_not_be_greater_than_max'),
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_category_id' => __('backend_messages.membership_category'),
            'price' => __('backend_messages.price_lowercase'),
            'price_type' => __('backend_messages.salary_type'),
            'price_value' => __('backend_messages.salary_percentage'),
            'duration_type' => __('backend_messages.duration_type'),
            'duration_value' => __('backend_messages.duration'),
            'visits_limit' => __('backend_messages.number_visits'),
            'start_date' => __('backend_messages.start_date'),
            'end_date' => __('backend_messages.end_date'),
            'guest_limit' => __('backend_messages.number_guests'),
            'freeze_limit' => __('backend_messages.number_freezes'),
            'active' => __('backend_messages.status_lowercase'),
            'translations' => __('backend_messages.translations'),
            'translations.*.name' => __('backend_messages.first_name'),
            'translations.*.description' => __('backend_messages.description_lowercase'),
            'trainers' => __('backend_messages.trainers'),
            'schedule_name_id' => __('backend_messages.schedule'),
            'trainers.*.trainer_id' => __('backend_messages.trainer_lowercase'),
            'trainers.*.price_type' => __('backend_messages.trainer_salary_type'),
            'trainers.*.price_value' => __('backend_messages.trainer_salary_percentage'),
        ];
    }
}
