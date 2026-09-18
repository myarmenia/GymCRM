<?php

namespace App\Http\Requests\Memberships;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMembershipCategoryRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'gym_id' => 'nullable|exists:gyms,id',
            'active' => 'sometimes|boolean',
            'slug' => 'required|string|max:255|unique:membership_categories,slug',
            'translations' => 'required|array|min:1',
            'translations.*.name' => 'required|string|max:255',
            'translations.*.description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('backend_messages.attribute_field_required'),
            'string' => __('backend_messages.attribute_must_be_string'),
            'max.string' => __('backend_messages.attribute_may_not_be_greater_than_max_characters'),
            'array' => __('backend_messages.attribute_must_be_array'),
            'boolean' => __('backend_messages.attribute_field_must_be_true_or_false'),
            'exists' => __('backend_messages.selected_attribute_invalid'),
            'unique' => __('backend_messages.attribute_has_already_been_taken'),
        ];
    }

    public function attributes(): array
    {
        return [
            'gym_id' => __('backend_messages.gym_lowercase'),
            'active' => __('backend_messages.status_lowercase'),
            'slug' => __('backend_messages.reference'),
            'translations' => __('backend_messages.translations'),
            'translations.*.name' => __('backend_messages.first_name'),
            'translations.*.description' => __('backend_messages.description_lowercase'),
        ];
    }
}
