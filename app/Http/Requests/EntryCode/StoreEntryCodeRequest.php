<?php

namespace App\Http\Requests\EntryCode;

use App\Models\Gym;
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
            'type'   => [
                'required',
                'string',
                'in:rfId,FaceId',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $allowedType = Gym::query()
                        ->whereKey($this->input('gym_id'))
                        ->value('entry_code_type');

                    if ($allowedType !== null && $value !== $allowedType) {
                        $fail('This gym only allows '.$allowedType.' entry codes.');
                    }
                },
            ],
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
