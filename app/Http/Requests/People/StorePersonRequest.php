<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // adjust if needed
    }

    public function rules()
    {
        return [
            'gym_id' => 'nullable|exists:gyms,id',
            'name' => 'nullable|string|max:255',
            'surname' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'type' => 'required|in:visitor,employee',
            'entry_code_id' => 'nullable|exists:entry_codes,id',
        ];
    }
}