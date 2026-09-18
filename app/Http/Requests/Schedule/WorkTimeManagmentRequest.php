<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class WorkTimeManagmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'id' => ['nullable', 'exists:schedule_names,id'],

            'week_days' => [
                'required',
                'array',
                function ($attribute, $value, $fail) {
                    $hasAtLeastOneWorkDay = false;

                    foreach ($value as $dayIndex => $day) {
                        $dayStart = $day['day_start_time'] ?? null;
                        $dayEnd = $day['day_end_time'] ?? null;

                        $breakStart = $day['break_start_time'] ?? null;
                        $breakEnd = $day['break_end_time'] ?? null;

                        $hasWorkStartOrEnd = $dayStart || $dayEnd;
                        $hasFullWorkDay = $dayStart && $dayEnd;
                        $hasBreak = $breakStart || $breakEnd;

                        if ($hasFullWorkDay) {
                            $hasAtLeastOneWorkDay = true;
                        }

                        if ($hasWorkStartOrEnd && !$hasFullWorkDay) {
                            $fail(
                                "week_days.$dayIndex.day_time",
                                __('backend_messages.start_and_end_working_hours_required')
                            );

                            continue;
                        }

                        if ($hasFullWorkDay && $dayEnd <= $dayStart) {
                            $fail(
                                "week_days.$dayIndex.day_time",
                                __('backend_messages.end_working_hours_must_be_later_than_start')
                            );
                        }

                        if ($hasBreak && !$hasFullWorkDay) {
                            $fail(
                                "week_days.$dayIndex.day_time",
                                __('backend_messages.working_hours_required_day_that_has_break')
                            );

                            continue;
                        }

                        if ($hasBreak) {
                            if (!$breakStart || !$breakEnd) {
                                $fail(
                                    "week_days.$dayIndex.break_time",
                                    __('backend_messages.break_start_and_end_times_required')
                                );
                            } elseif ($breakEnd <= $breakStart) {
                                $fail(
                                    "week_days.$dayIndex.break_time",
                                    __('backend_messages.break_end_time_must_be_later_than_its_start_time')
                                );
                            } elseif ($breakStart < $dayStart || $breakEnd > $dayEnd) {
                                $fail(
                                    "week_days.$dayIndex.break_time",
                                    __('backend_messages.break_must_fall_within_working_hours')
                                );
                            }
                        }
                    }

                    if (!$hasAtLeastOneWorkDay) {
                        $fail(
                            'week_days',
                            __('backend_messages.enter_start_and_end_working_hours_least_one_day')
                        );
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('backend_messages.name_field_required'),
        ];
    }
}