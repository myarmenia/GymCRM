<?php

namespace App\Http\Requests\TrainerCommissions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainerCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trainer_id' => ['required', 'integer', 'exists:users,id'],
            'membership_sale_id' => ['required', 'integer', 'exists:membership_sales,id'],
            'person_membership_id' => ['required', 'integer', 'exists:person_memberships,id'],
            'salary_type' => ['required', Rule::in(['fixed', 'percent'])],
            'salary_value' => ['required', 'numeric', 'min:0'],
            'salary_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['pending', 'paid'])],
            'paid_at' => ['nullable', 'date'],
            'is_kept' => ['sometimes', 'boolean'],
        ];
    }
}
