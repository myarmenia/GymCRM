<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:people,email',
            'password' => 'required|string|min:6',
            'phone' => 'required|string|max:50',
            'type' => 'required|in:visitor,employee',
            'entry_code_id' => 'nullable|exists:entry_codes,id',
        ];
    }
}