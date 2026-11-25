<?php

namespace App\Http\Requests\MobileAuth;

class MobileRegisterRequest extends MobilePhoneRequest
{
    public function rules(): array
    {
        // New-person identity requirements are checked after resolving the phone
        // across the synchronized databases. Existing identities are never edited.
        return [
            'phone' => $this->phoneRules(),
            'password' => ['required', 'string', 'max:4096', 'confirmed'],
            'name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
