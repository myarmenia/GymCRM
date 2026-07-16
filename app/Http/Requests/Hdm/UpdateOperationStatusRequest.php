<?php
// app/Http/Requests/Hdm/UpdateOperationStatusRequest.php

namespace App\Http\Requests\Hdm;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperationStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'operation_id' => 'required|integer|exists:hdm_operations,id',
            'status' => 'required|in:success,failed',
            'cashier_id' => 'nullable|integer|exists:hdm_cashiers,id',
            'new_session_key' => 'nullable|string|max:255',
            'crn' => 'nullable|string|max:100',
            'rseq' => 'nullable|max:100',
            'response' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'operation_id.required' => 'ID операции обязателен',
            'operation_id.exists' => 'Операция не найдена',
            'status.required' => 'Статус обязателен',
            'status.in' => 'Статус должен быть success или failed',
            'cashier_id.exists' => 'Кассир не найден',
        ];
    }
}
