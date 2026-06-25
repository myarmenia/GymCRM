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
            'membership_plan_ids.*' => ['integer', 'exists:membership_plans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute դաշտը պարտադիր է։',
            'numeric' => ':attribute դաշտը պետք է լինի թիվ։',
            'min.numeric' => ':attribute դաշտը պետք է լինի առնվազն :min։',
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
            'type' => 'զեղչի տեսակ',
            'value' => 'զեղչի արժեք',
            'start_date' => 'սկիզբ',
            'end_date' => 'ավարտ',
            'status' => 'կարգավիճակ',
            'translations' => 'թարգմանություններ',
            'translations.*.name' => 'անուն',
            'translations.*.description' => 'նկարագրություն',
            'membership_plan_ids' => 'աբոնեմենտներ',
            'membership_plan_ids.*' => 'աբոնեմենտ',
        ];
    }
}
