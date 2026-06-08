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

            'session_durations' => ['required', 'array', 'min:1'],
            'session_durations.*.id' => ['nullable', 'integer'],
            'session_durations.*.schedule_name_id' => ['required', 'integer', 'exists:schedule_names,id'],
            'session_durations.*.title' => ['required', 'string', 'max:255'],
            'session_durations.*.minutes' => ['required', 'integer', 'min:1'],
            'session_durations.*.type' => ['required', 'in:individual,group'],
            'session_durations.*.price' => ['nullable', 'numeric', 'min:0'],

            'session_durations.*.slots' => ['required', 'array', 'min:1'],
            'session_durations.*.slots.*.id' => ['nullable', 'integer'],
            'session_durations.*.slots.*.week_day' => ['required', 'string'],
            'session_durations.*.slots.*.start_time' => ['required', 'date_format:H:i'],
            'session_durations.*.slots.*.end_time' => ['required', 'date_format:H:i'],
        ];
    }
}