<?php

namespace App\Http\Requests\MobileProfile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMobileProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('people', 'phone')->ignore($this->user()->id),
            ],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
        ];
    }
}
