<?php

namespace App\Http\Requests\Hdm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHdmConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('owner') === true;
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'integer', Rule::exists('gyms', 'id')->whereNull('deleted_at')],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('hdm_configs', 'name')
                    ->where(fn ($query) => $query
                        ->where('gym_id', $this->integer('gym_id'))
                        ->whereNull('deleted_at')),
            ],
            'ip' => ['required', 'ip'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'password' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ];
    }
}
