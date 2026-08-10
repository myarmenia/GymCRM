<?php

namespace App\Http\Requests\MobileProfile;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonBiometricRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'height' => ['nullable', 'integer', 'min:1', 'max:300'],
            'weight' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'goal' => ['nullable', 'string', 'max:255'],
            'activity_level' => ['nullable', 'string', 'max:255'],
        ];
    }
}
