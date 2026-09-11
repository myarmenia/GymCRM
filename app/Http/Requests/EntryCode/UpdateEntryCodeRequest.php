<?php

namespace App\Http\Requests\EntryCode;

use App\Models\EntryCode;
use App\Models\Gym;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEntryCodeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $entryCode = EntryCode::query()->find($this->route('id'));

        if (!$entryCode) {
            return;
        }

        $this->merge([
            'gym_id' => $this->input('gym_id') ?? $entryCode->gym_id,
            'type' => $this->input('type') ?? $entryCode->type,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $entryCodeId = $this->route('id'); // կամ $this->entry_code

        return [
            'gym_id'    => 'required|exists:gyms,id',
            'token'     => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('entry_codes', 'token')
                    ->where('gym_id', $this->gym_id)
                    ->ignore($entryCodeId),
            ],
            'status'    => 'sometimes|boolean',
            'activation'=> 'sometimes|boolean',
            'type'      => [
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
        ];
    }

    public function messages(): array
    {
        return [
            'token.unique' => 'Այս token-ն արդեն գոյություն ունի այս մարզադահլիճի համար:',
        ];
    }
}
