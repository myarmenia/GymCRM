<?php

namespace App\Http\Requests\MobileAuth;

use Illuminate\Foundation\Http\FormRequest;

class ChangeMobilePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:4096'],
            'password' => ['required', 'string', 'min:8', 'max:4096', 'confirmed', 'different:current_password'],
        ];
    }
}
