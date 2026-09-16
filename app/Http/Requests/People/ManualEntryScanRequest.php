<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualEntryScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('manager') ?? false;
    }

    public function rules(): array
    {
        return [
            'entry_code' => ['required', 'string', 'max:255'],
            'direction' => ['required', Rule::in(['enter'])],
            'type' => ['required', Rule::in(['rfId'])],
        ];
    }
}
