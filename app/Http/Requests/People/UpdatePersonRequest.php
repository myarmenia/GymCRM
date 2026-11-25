<?php

namespace App\Http\Requests\People;

use App\Models\EntryPermission;
use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $personId = $this->route('id');
        $gymId = $this->user()?->gym_id;
        $currentIds = EntryPermission::query()->where('relation_type', Person::class)
            ->where('relation_id', $personId)->whereNull('deleted_at')
            ->whereHas('entryCode', fn ($query) => $query->where('gym_id', $gymId))
            ->pluck('entry_code_id');

        return [
            'type' => ['required', Rule::in(['visitor', 'guest', 'newcomer'])],
            'entry_code_id' => ['nullable', Rule::exists('entry_codes', 'id')->where(function ($query) use ($gymId, $currentIds): void {
                $query->where('gym_id', $gymId)->where('status', true)
                    ->where(fn ($available) => $available->where('activation', false)->orWhereIn('id', $currentIds));
            })],
        ];
    }
}
