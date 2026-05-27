<?php

namespace App\Http\Requests\Partners;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'contract_number' => 'required|string|max:255',
            'address' => 'required|string',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'contact_full_name' => 'required|string|max:255',
            'contact_phone' => 'nullable|string|max:50', // Միակ ընտրովի դաշտը
        ];
    }
}
