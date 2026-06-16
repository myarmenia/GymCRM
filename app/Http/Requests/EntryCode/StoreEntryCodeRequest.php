<?php

namespace App\Http\Requests\EntryCode;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEntryCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gym_id' => 'required|exists:gyms,id',
            'token'  => [
                'required',
                'string',
                'max:255',
                Rule::unique('entry_codes', 'token')->where('gym_id', $this->gym_id),
            ],
            'type'   => 'required|string|in:rfId,FaceId',
            'activation' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'token.unique' => 'Այս token-ն արդեն գոյություն ունի այս մարզադահլիճի համար:',
        ];
    }
}