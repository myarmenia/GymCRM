<?php

namespace App\Http\Requests\MembershipSales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembershipSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('discount_type') && $this->input('discount_type') === '') {
            $data['discount_type'] = null;
        }

        if ($this->has('discount_value') && $this->input('discount_value') === '') {
            $data['discount_value'] = null;
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'membership_discount_ids' => ['nullable', 'array'],
            'membership_discount_ids.*' => ['integer', 'exists:discounts,id'],
            'apply_discount' => ['sometimes', 'boolean'],
            'discount_type' => ['nullable', Rule::in($this->discountTypes())],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->boolean('apply_discount')) {
                return;
            }

            if (!$this->filled('discount_type')) {
                $validator->errors()->add('discount_type', 'Զեղչի տեսակը պարտադիր է։');
            }

            if (!$this->filled('discount_value')) {
                $validator->errors()->add('discount_value', 'Զեղչի արժեքը պարտադիր է։');
            }

            if ($this->input('discount_type') === 'percent' && (float) $this->input('discount_value') > 100) {
                $validator->errors()->add('discount_value', 'Տոկոսային զեղչը չի կարող լինել 100-ից մեծ։');
            }
        });
    }

    public function messages(): array
    {
        return [
            'integer' => ':attribute դաշտը պետք է լինի ամբողջ թիվ։',
            'numeric' => ':attribute դաշտը պետք է լինի թիվ։',
            'min.numeric' => ':attribute դաշտը պետք է լինի առնվազն :min։',
            'array' => ':attribute դաշտը պետք է լինի ցուցակ։',
            'boolean' => ':attribute դաշտը պետք է լինի այո կամ ոչ։',
            'exists' => 'Ընտրված :attribute-ը անվավեր է։',
            'in' => 'Ընտրված :attribute-ը անվավեր է։',
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_discount_ids' => 'աբոնեմենտի զեղչեր',
            'membership_discount_ids.*' => 'աբոնեմենտի զեղչ',
            'discount_type' => 'զեղչի տեսակ',
            'discount_value' => 'զեղչի արժեք',
        ];
    }

    protected function discountTypes(): array
    {
        $migration = file_get_contents(
            base_path('database/migrations/2026_06_08_000004_create_membership_sales_table.php')
        );

        if (!preg_match("/enum\\('discount_type',\\s*\\[(.*?)\\]\)/s", $migration, $matches)) {
            return [];
        }

        preg_match_all("/'([^']+)'/", $matches[1], $values);

        return $values[1] ?? [];
    }
}
