<?php

namespace App\Http\Requests\Trainer;
    
use Illuminate\Foundation\Http\FormRequest;

class StoreTrainerScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'schedule_names' => ['required', 'array', 'min:1'],
            'schedule_names.*' => ['required', 'integer', 'exists:schedule_names,id'],

            'session_durations' => ['nullable', 'array'],
            'session_durations.*.id' => ['nullable', 'integer'],
            'session_durations.*.schedule_name_id' => ['nullable', 'integer', 'exists:schedule_names,id'],
            'session_durations.*.title' => ['nullable', 'string', 'max:255'],
            'session_durations.*.minutes' => ['nullable', 'integer', 'min:1'],
            'session_durations.*.type' => ['nullable', 'in:individual,group'],
            'session_durations.*.price' => ['nullable', 'numeric', 'min:0'],

            'session_durations.*.slots' => ['nullable', 'array', 'min:1'],
            'session_durations.*.slots.*.id' => ['nullable', 'integer'],
            'session_durations.*.slots.*.week_day' => ['nullable', 'string'],
            'session_durations.*.slots.*.start_time' => ['nullable', 'date_format:H:i'],
            'session_durations.*.slots.*.end_time' => ['nullable', 'date_format:H:i'],
        ];
    }
}