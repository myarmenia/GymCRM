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
            'required' => ':attribute դաշտը պարտադիր է։',
            'string' => ':attribute դաշտը պետք է լինի տեքստ։',
            'max' => ':attribute դաշտը չի կարող գերազանցել :max նիշը։',
            'email' => ':attribute դաշտը պետք է լինի վավեր էլ. հասցե։',
            'date' => ':attribute դաշտը պետք է լինի վավեր ամսաթիվ։',
            'in' => 'Ընտրված :attribute-ը անվավեր է։',
            'entry_code_id.exists' => 'Ընտրված մուտքի կոդը չի գտնվել։ Ստեղծիր',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'անուն',
            'surname' => 'ազգանուն',
            'email' => 'էլ. հասցե',
            'phone' => 'հեռախոսահամար',
            'entry_code_id' => 'մուտքի կոդ',
            'birth_date' => 'ծննդյան ամսաթիվ',
            'gender' => 'սեռ',
        ];
    }
}
