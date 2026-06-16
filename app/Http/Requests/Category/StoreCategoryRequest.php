<?php

namespace App\Http\Requests\Category;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        return [
            'type' => ['required', 'in:category,subcategory'],

            'parent_id' => [
                'nullable',
                'required_if:type,subcategory',
                'exists:inventory_categories,id',
            ],

            'icon' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'boolean'],

            //'translations.en.name' => ['required', 'string', 'max:255'],
            //'translations.ru.name' => ['required', 'string', 'max:255'],
            'translations.hy.name' => ['required', 'string', 'max:255'],
        ];
    }
}
