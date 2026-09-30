<?php

namespace App\Http\Requests\Hdm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHdmConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('owner') === true;
    }

    public function rules(): array
    {
        $config = $this->route('hdmConfig');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('hdm_configs', 'name')
                    ->where(fn ($query) => $query
                        ->where('gym_id', $config->gym_id)
                        ->whereNull('deleted_at'))
                    ->ignore($config->id),
            ],
            'ip' => ['required', 'ip'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'password' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ];
    }
}
