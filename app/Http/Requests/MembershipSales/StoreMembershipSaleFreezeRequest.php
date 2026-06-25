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
            'required' => ':attribute դաշտը պարտադիր է։',
            'date' => ':attribute դաշտը պետք է լինի վավեր ամսաթիվ։',
            'after_or_equal' => ':attribute-ը պետք է լինի :date-ից ոչ շուտ։',
            'string' => ':attribute դաշտը պետք է լինի տեքստ։',
        ];
    }

    public function attributes(): array
    {
        return [
            'start_date' => 'սառեցման սկիզբ',
            'end_date' => 'սառեցման ավարտ',
            'notes' => 'նշումներ',
        ];
    }
}
