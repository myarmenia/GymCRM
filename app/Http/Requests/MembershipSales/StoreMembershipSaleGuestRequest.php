<?php

namespace App\Http\Requests\MembershipSales;

use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipSaleGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nullableFields = [
            'surname',
            'email',
            'birth_date',
            'gender',
        ];

        $data = [];

        foreach ($nullableFields as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $data[$field] = null;
            }
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'entry_code_id' => ['required', 'integer', 'exists:entry_codes,id'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('validation.required'),
            'string' => __('validation.string'),
            'max' => __('validation.max.string'),
            'email' => __('validation.email'),
            'date' => __('validation.date'),
            'in' => __('validation.exists'),
            'entry_code_id.exists' => __('backend.membership_sales.entry_code_not_found_create'),
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('backend.attributes.name'),
            'surname' => __('backend.attributes.surname'),
            'email' => __('backend.attributes.email'),
            'phone' => __('backend.attributes.phone'),
            'entry_code_id' => __('backend.attributes.entry_code'),
            'birth_date' => __('backend.attributes.birth_date'),
            'gender' => __('backend.attributes.gender'),
        ];
    }
}
