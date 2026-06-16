<?php

namespace App\Http\Requests\EntryCode;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntryCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $entryCodeId = $this->route('id'); // կամ $this->entry_code

        return [
            'gym_id'    => 'sometimes|exists:gyms,id',
            'token'     => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('entry_codes', 'token')
                    ->where('gym_id', $this->gym_id ?? $this->entry_code->gym_id)
                    ->ignore($entryCodeId),
            ],
            'status'    => 'sometimes|boolean',
            'activation'=> 'sometimes|boolean',
            'type'      => 'sometimes|string|in:rfId,FaceId',
        ];
    }

    public function messages(): array
    {
        return [
            'token.unique' => 'Այս token-ն արդեն գոյություն ունի այս մարզադահլիճի համար:',
        ];
    }
}