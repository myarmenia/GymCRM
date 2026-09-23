<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:entry'],
            'membership_id' => ['nullable', 'integer', 'exists:person_memberships,id'],
            'manual_datetime' => ['required', 'date_format:Y-m-d\TH:i'],
        ];
    }
}
