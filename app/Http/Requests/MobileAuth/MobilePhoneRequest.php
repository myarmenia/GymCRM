<?php

namespace App\Http\Requests\MobileAuth;

use App\Rules\PersonPhone;
use App\Services\MembershipSales\ContactNotePhone;
use Illuminate\Foundation\Http\FormRequest;

abstract class MobilePhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (is_string($phone) && ContactNotePhone::isValidPersonPhone($phone)) {
            $this->merge(['phone' => ContactNotePhone::canonical($phone)]);
        }
    }

    protected function phoneRules(): array
    {
        return ['required', 'string', 'max:50', new PersonPhone];
    }
}
