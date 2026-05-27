<?php

namespace App\Http\Requests\Partners;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'sometimes|required|string|max:255',
            'account_number'    => 'sometimes|required|string|max:255',
            'contract_number'   => 'sometimes|required|string|max:255',
            'address'           => 'sometimes|required|string',
            'phone'             => 'sometimes|required|string|max:50',
            'email'             => 'sometimes|required|email|max:255',
            'contact_full_name' => 'sometimes|required|string|max:255',
            'contact_phone'     => 'nullable|string|max:50',
        ];
    }
}
