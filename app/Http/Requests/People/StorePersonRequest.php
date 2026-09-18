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
            'email' => 'required|email|max:255|unique:people,email',
            'password' => 'required|string|min:6',
            'phone' => ['required', 'string', 'max:50', Rule::unique('people', 'phone')],
            'type' => 'required|in:visitor,guest',
            'entry_code_id' => [
                'required',
                Rule::exists('entry_codes', 'id')->where(function ($query): void {
                    $query->where('status', true)->where('activation', false);

                    if ($this->user()?->gym_id) {
                        $query->where('gym_id', $this->user()->gym_id);
                    }
                }),
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
            'phone.unique' => __('backend_messages.person_with_this_phone_number_already_exists'),
        ];
    }
}
