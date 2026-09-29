<?php

namespace App\Http\Requests\Hdm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHdmCashierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('owner') === true;
    }

    public function rules(): array
    {
        $config = $this->route('hdmConfig');

        return [
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('gym_id', $config->gym_id)
                    ->whereNull('deleted_at')),
                Rule::unique('hdm_cashiers', 'user_id')->where(fn ($query) => $query
                    ->where('hdm_config_id', $config->id)
                    ->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'login' => [
                'required', 'string', 'max:255',
                Rule::unique('hdm_cashiers', 'login')->where(fn ($query) => $query
                    ->where('hdm_config_id', $config->id)
                    ->whereNull('deleted_at')),
            ],
            'pin' => ['required', 'string', 'min:1', 'max:255'],
            'status' => ['required', 'boolean'],
        ];
    }
}
