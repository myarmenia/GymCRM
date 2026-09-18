<?php

namespace App\Http\Requests\Gyms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'entry_code_type' => ['required', 'string', Rule::in(['rfId', 'FaceId'])],
            'trainer_salary_mode' => ['required', Rule::in(['prepaid', 'postpaid'])],
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
            'trainer_salary_mode.required' => __('backend_messages.select_how_trainer_salary_calculated'),
            'trainer_salary_mode.in' => __('backend_messages.trainer_salary_calculation_method_invalid'),
        ];
    }
}
