<?php

namespace App\Http\Requests\OwnerSales;

use App\Models\OwnerSale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOwnerSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('owner') === true;
    }

    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'integer', Rule::exists('gyms', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_type' => ['required', Rule::in(OwnerSale::PAYMENT_TYPES)],
            'payment_status' => ['required', Rule::in(OwnerSale::PAYMENT_STATUSES)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
