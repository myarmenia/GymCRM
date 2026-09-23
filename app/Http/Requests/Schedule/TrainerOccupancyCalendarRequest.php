<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class TrainerOccupancyCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'week' => $this->week ?: null,
            'trainer_id' => $this->trainer_id ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'week' => ['nullable', 'date'],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'week.date' => __('backend_messages.week_date_invalid'),
            'trainer_id.integer' => __('backend_messages.selected_trainer_invalid'),
            'trainer_id.exists' => __('backend_messages.selected_trainer_not_found'),
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'week' => $validated['week'] ?? null,
            'trainer_id' => $validated['trainer_id'] ?? null,
        ];
    }
}
