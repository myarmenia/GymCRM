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
            'trainer_id.required' => 'Ընտրեք նոր մարզիչը։',
            'trainer_id.integer' => 'Ընտրված մարզիչը սխալ է։',
            'trainer_id.exists' => 'Ընտրված մարզիչը չի գտնվել։',
        ];
    }

    public function attributes(): array
    {
        return [
            'trainer_id' => 'մարզիչ',
        ];
    }
}
