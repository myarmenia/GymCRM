<?php

namespace App\Http\Requests\Memberships;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembershipCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'gym_id' => 'nullable|exists:gyms,id',
            'active' => 'sometimes|boolean',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('membership_categories', 'slug')->ignore($id)],
            'translations' => 'sometimes|array',
            'translations.*.name' => 'required|string|max:255',
            'translations.*.description' => 'nullable|string',
        ];
    }
}