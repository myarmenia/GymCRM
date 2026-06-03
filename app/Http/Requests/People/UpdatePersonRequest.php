<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gym_id' => 'nullable|exists:gyms,id',
            'name' => 'nullable|string|max:255',
            'surname' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'type' => 'sometimes|in:visitor,employee',
            'entry_code_id' => 'nullable|exists:entry_codes,id',
        ];
    }
}