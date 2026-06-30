<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['entry', 'exit'])],
            'membership_id' => ['nullable', 'integer', 'exists:person_memberships,id'],
            'manual_datetime' => ['required', 'date_format:Y-m-d\TH:i'],
        ];
    }
}
