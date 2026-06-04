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
                                'Աշխատանքային ժամի սկիզբն ու ավարտը պարտադիր են։'
                            );

                            continue;
                        }

                        if ($hasFullWorkDay && $dayEnd <= $dayStart) {
                            $fail(
                                "week_days.$dayIndex.day_time",
                                'Աշխատանքային ավարտը պետք է մեծ լինի սկիզբից։'
                            );
                        }

                        if ($hasBreak && !$hasFullWorkDay) {
                            $fail(
                                "week_days.$dayIndex.day_time",
                                'Ընդմիջման ժամ ավելացնելու դեպքում տվյալ օրվա աշխատանքային ժամերը պարտադիր են։'
                            );

                            continue;
                        }

                        if ($hasBreak) {
                            if (!$breakStart || !$breakEnd) {
                                $fail(
                                    "week_days.$dayIndex.break_time",
                                    'Ընդմիջման սկիզբն ու ավարտը պարտադիր են։'
                                );
                            } elseif ($breakEnd <= $breakStart) {
                                $fail(
                                    "week_days.$dayIndex.break_time",
                                    'Ընդմիջման ավարտը պետք է մեծ լինի սկիզբից։'
                                );
                            } elseif ($breakStart < $dayStart || $breakEnd > $dayEnd) {
                                $fail(
                                    "week_days.$dayIndex.break_time",
                                    'Ընդմիջման ժամը պետք է լինի աշխատանքային ժամերի միջակայքում։'
                                );
                            }
                        }
                    }

                    if (!$hasAtLeastOneWorkDay) {
                        $fail(
                            'week_days',
                            'Անհրաժեշտ է լրացնել առնվազն մեկ օրվա աշխատանքային ժամի սկիզբը և ավարտը։'
                        );
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Անվանում դաշտը պարտադիր է։',
        ];
    }
}