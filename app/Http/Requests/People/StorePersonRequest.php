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
            'image' => 'nullable|image|max:2048',
            'email' => 'nullable|email|max:255|unique:people,email',
            'password' => 'nullable|string|min:6',
            'phone' => ['nullable', 'string', 'max:50', Rule::unique('people', 'phone')],
            'type' => 'required|in:visitor,guest',
            'entry_code_mode' => ['required', Rule::in(['existing', 'new'])],
            'entry_code_id' => [
                Rule::requiredIf(fn (): bool => $this->input('entry_code_mode') === 'existing'),
                'nullable',
                Rule::exists('entry_codes', 'id')->where(function ($query): void {
                    $query->where('status', true)->where('activation', false);

                    if ($this->user()?->gym_id) {
                        $query->where('gym_id', $this->user()->gym_id);
                    }
                }),
            ],
            'entry_code_token' => [
                Rule::requiredIf(fn (): bool => $this->input('entry_code_mode') === 'new'),
                'nullable',
                'string',
                'max:255',
                Rule::unique('entry_codes', 'token')->where(
                    fn ($query) => $query->where('gym_id', $this->user()?->gym_id),
                ),
            ],
            'birth_date' => 'required|date',
            'gender' => 'nullable|string|in:male,female',
        ];
    }

    public function messages(): array
    {
        return [
            'entry_code_id.required' => __('backend_messages.entry_code_required'),
            'entry_code_id.exists' => __('backend_messages.selected_entry_code_not_found_create_one'),
            'entry_code_token.required' => __('backend_messages.entry_code_required'),
            'entry_code_token.unique' => __('backend_messages.this_token_already_exists_this_gym'),
            'phone.unique' => __('backend_messages.person_with_this_phone_number_already_exists'),
        ];
    }
}
