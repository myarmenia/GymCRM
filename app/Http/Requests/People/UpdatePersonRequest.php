<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $personId = $this->route('id');

        return [
            'name' => 'sometimes|required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('people', 'email')->ignore($personId),
            ],
            'password' => 'nullable|string|min:6',   // update-ում password-ը կարող է բացակայել (չփոխել)
            'phone' => 'sometimes|required|string|max:50',
            'type' => 'sometimes|required|in:visitor,employee',
            'entry_code_id' => 'nullable|exists:entry_codes,id',
        ];
    }
}