<?php

namespace App\Http\Requests\People;

use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $personId = $this->route('id');
        $currentEntryCodeId = DB::table('entry_permissions')
            ->where('relation_type', Person::class)
            ->where('relation_id', $personId)
            ->whereNull('deleted_at')
            ->latest('id')
            ->value('entry_code_id');

        return [
            'name' => 'sometimes|required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('people', 'email')->ignore($personId),
            ],
            'password' => 'nullable|string|min:6',
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('people', 'phone')->ignore($personId),
            ],
            'type' => 'sometimes|required|in:visitor,guest',
            'entry_code_id' => [
                'nullable',
                Rule::exists('entry_codes', 'id')->where(function ($query) use ($currentEntryCodeId): void {
                    $query
                        ->where('status', true)
                        ->where(function ($available) use ($currentEntryCodeId): void {
                            $available->where('activation', false);
                            if ($currentEntryCodeId !== null) {
                                $available->orWhere('id', $currentEntryCodeId);
                            }
                        });

                    if ($this->user()?->gym_id) {
                        $query->where('gym_id', $this->user()->gym_id);
                    }
                }),
            ],
            'birth_date' => 'sometimes|required|date',
            'gender' => 'nullable|string|in:male,female',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Այս հեռախոսահամարով անձ արդեն գոյություն ունի։',
        ];
    }
}
