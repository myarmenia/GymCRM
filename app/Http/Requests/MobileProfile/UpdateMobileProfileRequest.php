<?php

namespace App\Http\Requests\MobileProfile;

use App\Http\Requests\MobileAuth\MobilePhoneRequest;
use App\Rules\PersonPhone;
use Illuminate\Validation\Rule;

class UpdateMobileProfileRequest extends MobilePhoneRequest
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
                new PersonPhone,
                Rule::unique('people', 'phone')->ignore($this->user()->id),
            ],
            'birth_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
        ];
    }
}
