<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'phone' => ['nullable', 'regex:/^[0-9]{9,14}$/'],
            'password' => ['nullable', 'confirmed', 'min:8'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_expire_at' => ['nullable', 'date'],
            'birth_date' => ['nullable', 'date'],
            'roles' => ['required', 'array'],
            'gym_id' => ['nullable', 'exists:gyms,id'],
            'active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'Please select at least one role',
        ];
    }
}
