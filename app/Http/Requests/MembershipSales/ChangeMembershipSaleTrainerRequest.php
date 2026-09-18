<?php

namespace App\Http\Requests\MembershipSales;

use Illuminate\Foundation\Http\FormRequest;

class ChangeMembershipSaleTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('trainer_id') === '') {
            $this->merge([
                'trainer_id' => null,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'trainer_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'trainer_id.required' => __('backend.membership_sales.select_new_trainer'),
            'trainer_id.integer' => __('backend.membership_sales.invalid_trainer'),
            'trainer_id.exists' => __('backend.membership_sales.trainer_not_found'),
        ];
    }

    public function attributes(): array
    {
        return [
            'trainer_id' => __('backend.attributes.trainer'),
        ];
    }
}
