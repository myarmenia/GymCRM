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
            'price_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'price_value' => ['nullable', 'numeric', 'min:0'],

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
                'min:1',
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

                    if (!$this->duration_value) {
                        $validator->errors()->add(
                            'duration_value',
                            __('membership.duration_value_required')
                        );
                    }

                    break;

                case 'visit':

                    if (!$this->visits_limit) {
                        $validator->errors()->add(
                            'visits_limit',
                            __('membership.visits_limit_required')
                        );
                    }

                    if (!$this->duration_value) {
                        $validator->errors()->add(
                            'duration_value',
                            __('membership.membership_period_required')
                        );
                    }

                    break;

                case 'period':

                    if (!$this->start_date) {
                        $validator->errors()->add(
                            'start_date',
                            __('membership.start_date_required')
                        );
                    }

                    if (!$this->end_date) {
                        $validator->errors()->add(
                            'end_date',
                            __('membership.end_date_required')
                        );
                    }

                    break;
            }
        });
    }
}
