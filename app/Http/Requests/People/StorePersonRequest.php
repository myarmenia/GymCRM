<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
            'email' => 'required|email|max:255|unique:people,email',
            'password' => 'required|string|min:6',
            'phone' => ['required', 'string', 'max:50', Rule::unique('people', 'phone')],
            'type' => 'required|in:visitor,guest',
            'entry_code_id' => 'required|exists:entry_codes,id',
            'birth_date' => 'required|date',
            'gender' => 'nullable|string|in:male,female,other',
            'mobile_deleted' => 'sometimes|boolean',
            'fcm_token' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'entry_code_id.required' => 'Մուտքի կոդը պարտադիր է։',
            'entry_code_id.exists' => 'Ընտրված մուտքի կոդը չի գտնվել։ Ստեղծիր',
            'phone.unique' => 'Այս հեռախոսահամարով անձ արդեն գոյություն ունի։',
        ];
    }
}
