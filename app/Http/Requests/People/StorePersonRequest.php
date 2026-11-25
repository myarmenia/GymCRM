<?php

namespace App\Http\Requests\People;

use App\Models\Person;
use App\Rules\PersonPhone;
use App\Services\MembershipSales\ContactNotePhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->routeIs('person.newcomer.store')) {
            $contact = $this->route('contactProfile');
            $this->merge(['type' => 'newcomer', 'phone' => $contact->phone_number]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $existing = Person::query()->where('phone', ContactNotePhone::canonical((string) $this->input('phone')))->first();

        return [
            'name' => $existing ? ['exclude'] : 'required|string|max:255',
            'surname' => 'nullable|string|max:255',
            'image' => 'nullable|image|max:2048',
            'email' => $existing ? ['exclude'] : 'nullable|email|max:255|unique:people,email',
            'password' => 'nullable|string|min:6',
            'phone' => ['required', 'string', 'max:50', new PersonPhone,
                function (string $attribute, mixed $value, \Closure $fail) use ($existing): void {
                    if ($existing?->gyms()->where('gyms.id', $this->user()?->gym_id)->exists()) {
                        $fail(__('backend_messages.person_with_this_phone_number_already_exists'));
                    }
                },
            ],
            'type' => ['required', Rule::in($this->routeIs('person.newcomer.store')
                ? ['newcomer'] : ['visitor', 'guest'])],
            'entry_code_mode' => ['required', Rule::in(['existing', 'new'])],
            'entry_code_id' => [
                Rule::requiredIf(fn (): bool => $this->input('entry_code_mode') === 'existing'),
                'nullable',
                Rule::exists('entry_codes', 'id')->where(function ($query): void {
                    $query->where('status', true)->where('activation', false);

                    if ($this->user()?->gym_id) {
                        $query->where('gym_id', $this->user()->gym_id);
                    }
                }),
            ],
            'entry_code_token' => [
                Rule::requiredIf(fn (): bool => $this->input('entry_code_mode') === 'new'),
                'nullable',
                'string',
                'max:255',
                Rule::unique('entry_codes', 'token')->where(
                    fn ($query) => $query->where('gym_id', $this->user()?->gym_id),
                ),
            ],
            'birth_date' => $existing ? ['exclude'] : 'required|date',
            'gender' => 'nullable|string|in:male,female',
            'passport_series' => 'nullable|string|max:100',
            'passport_issued_by' => 'nullable|string|max:255',
            'passport_issued_at' => 'nullable|date',
            'address' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'entry_code_id.required' => __('backend_messages.entry_code_required'),
            'entry_code_id.exists' => __('backend_messages.selected_entry_code_not_found_create_one'),
            'entry_code_token.required' => __('backend_messages.entry_code_required'),
            'entry_code_token.unique' => __('backend_messages.this_token_already_exists_this_gym'),
            'phone.unique' => __('backend_messages.person_with_this_phone_number_already_exists'),
        ];
    }
}
