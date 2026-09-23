<?php

namespace App\Http\Requests\MembershipSales;

use Illuminate\Foundation\Http\FormRequest;

class StoreMembershipSaleFreezeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && $this->input('notes') === '') {
            $this->merge([
                'notes' => null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('validation.required'),
            'date' => __('validation.date'),
            'after_or_equal' => __('validation.after_or_equal'),
            'string' => __('validation.string'),
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => __('backend.attributes.freeze_start'),
            'end_date' => __('backend.attributes.freeze_end'),
            'notes' => __('backend.attributes.notes'),
        ];
    }
}
