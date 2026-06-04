<?php

namespace App\Http\Requests\EntryCode;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEntryCodeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_id' => 'sometimes|exists:clients,id',
            'gym_id'    => 'sometimes|exists:gyms,id',
            'token'     => 'sometimes|string|max:255|unique:entry_codes,token,' . $this->route('id'),
            'status'    => 'sometimes|boolean',
            'activation'=> 'sometimes|boolean',
            'type'      => 'sometimes|string|max:255',
        ];
    }
}