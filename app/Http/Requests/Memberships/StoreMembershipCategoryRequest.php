<?php

namespace App\Http\Requests\Memberships;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'gym_id' => 'nullable|exists:gyms,id',
            'active' => 'sometimes|boolean',
            'slug' => 'required|string|max:255|unique:membership_categories,slug',
            'translations' => 'required|array|min:1',
            'translations.*.name' => 'required|string|max:255',
            'translations.*.description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute դաշտը պարտադիր է։',
            'string' => ':attribute դաշտը պետք է լինի տեքստ։',
            'max.string' => ':attribute դաշտը չի կարող գերազանցել :max նիշը։',
            'array' => ':attribute դաշտը պետք է լինի ցուցակ։',
            'boolean' => ':attribute դաշտը պետք է լինի այո կամ ոչ։',
            'exists' => 'Ընտրված :attribute-ը անվավեր է։',
            'unique' => ':attribute դաշտի արժեքը արդեն օգտագործվում է։',
        ];
    }

    public function attributes(): array
    {
        return [
            'gym_id' => 'մարզասրահ',
            'active' => 'կարգավիճակ',
            'slug' => 'հղում',
            'translations' => 'թարգմանություններ',
            'translations.*.name' => 'անուն',
            'translations.*.description' => 'նկարագրություն',
        ];
    }
}
