<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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

        return [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'regex:/^[0-9]{9,14}$/'],
            'password' => ['required', 'confirmed', 'min:8'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'passport_expire_at' => ['nullable', 'date'],
            'birth_date' => ['nullable', 'date'],
            'roles' => ['required', 'array'],
            'active' => ['boolean'],
            'gym_id' => [
                Rule::requiredIf(function () {
                    return in_array('owner', auth()->user()->roles->pluck('name')->toArray());
                }),
                'nullable',
                'exists:gyms,id'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'Please select at least one role',
        ];
    }
}
