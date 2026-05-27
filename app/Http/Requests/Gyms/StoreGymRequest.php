<?php

namespace App\Http\Requests\Gyms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Validation\Validator;

class StoreGymRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Լոգ ենք անում, որպեսզի տեսնենք՝ հարցումը ընդհանրապես հասա՞վ այստեղ, թե ոչ
        Log::info('1. StoreGymRequest authorized checking...', $this->all());
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        Log::info('2. StoreGymRequest rules() loaded.');

        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]+$/'],
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The gym name is required.',
            'address.required' => 'The gym address is required.',
            'phone.regex' => 'The phone number format is invalid. It should only contain numbers, spaces, plus, and minus signs.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }

    /**
     * Եթե վալիդացիան ձախողվի, այս մեթոդը ավտոմատ կաշխատի
     */
    protected function failedValidation(Validator $validator)
    {
        Log::error('❌ Gym Validation FAILED:', $validator->errors()->toArray());
        parent::failedValidation($validator);
    }
}
