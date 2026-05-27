<?php

namespace App\Http\Requests\Gyms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGymRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Բռնում ենք խմբագրվող հյուրանոցի ID-ն URL-ից
        $gymId = $this->route('gym');

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

    public function messages(): array
    {
        return [
            'name.required' => 'The gym name is required.',
            'address.required' => 'The gym address is required.',
            'phone.regex' => 'The phone number format is invalid.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }
}
