<?php

namespace App\Http\Requests\MobileAuth;

class MobileLoginRequest extends MobilePhoneRequest
{
    public function rules(): array
    {
        return [
            'phone' => $this->phoneRules(),
            'password' => ['required', 'string', 'max:4096'],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
