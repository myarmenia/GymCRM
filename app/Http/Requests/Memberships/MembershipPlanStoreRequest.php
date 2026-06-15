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

    public function messages(): array
    {
        return [
            'required' => ':attribute դաշտը պարտադիր է։',
            'integer' => ':attribute դաշտը պետք է լինի ամբողջ թիվ։',
            'numeric' => ':attribute դաշտը պետք է լինի թիվ։',
            'min.numeric' => ':attribute դաշտը պետք է լինի առնվազն :min։',
            'min.integer' => ':attribute դաշտը պետք է լինի առնվազն :min։',
            'array' => ':attribute դաշտը պետք է լինի ցուցակ։',
            'boolean' => ':attribute դաշտը պետք է լինի այո կամ ոչ։',
            'date' => ':attribute դաշտը պետք է լինի վավեր ամսաթիվ։',
            'after_or_equal' => ':attribute-ը պետք է լինի :date-ից ոչ շուտ։',
            'exists' => 'Ընտրված :attribute-ը անվավեր է։',
            'in' => 'Ընտրված :attribute-ը անվավեր է։',
            'string' => ':attribute դաշտը պետք է լինի տեքստ։',
            'max.string' => ':attribute դաշտը չի կարող գերազանցել :max նիշը։',
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_category_id' => 'աբոնեմենտի կատեգորիա',
            'price' => 'գին',
            'duration_type' => 'տևողության տեսակ',
            'duration_value' => 'տևողություն',
            'visits_limit' => 'այցելությունների քանակ',
            'start_date' => 'սկիզբ',
            'end_date' => 'ավարտ',
            'guest_limit' => 'հյուրերի քանակ',
            'freeze_limit' => 'սառեցման քանակ',
            'active' => 'կարգավիճակ',
            'translations' => 'թարգմանություններ',
            'translations.*.name' => 'անուն',
            'translations.*.description' => 'նկարագրություն',
            'trainers' => 'մարզիչներ',
        ];
    }
}
