<?php

namespace App\Http\Requests\Gyms;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreGymRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasRole('owner');
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
            'entry_code_type' => ['required', 'string', Rule::in(['rfId', 'FaceId'])],
            'trainer_salary_mode' => ['required', Rule::in(['prepaid', 'postpaid'])],
            'language_codes' => ['required', 'array', 'min:1'],
            'language_codes.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('langs', 'code'),
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
            'trainer_salary_mode.required' => __('backend_messages.select_how_trainer_salary_calculated'),
            'trainer_salary_mode.in' => __('backend_messages.trainer_salary_calculation_method_invalid'),
            'language_codes.required' => __('backend_messages.select_at_least_one_gym_language'),
            'language_codes.min' => __('backend_messages.select_at_least_one_gym_language'),
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
