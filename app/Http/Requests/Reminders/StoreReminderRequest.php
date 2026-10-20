<?php

namespace App\Http\Requests\Reminders;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recipient_ids' => array_values(array_unique(array_filter(
                array_map('intval', (array) $this->input('recipient_ids', []))
            ))),
            'about_id' => $this->input('about_id') ?: null,
            'title' => $this->input('title') ?: null,
            'description' => $this->input('description') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('reminder_categories', 'id')->where('active', true),
            ],
            'recipient_ids' => ['required', 'array', 'min:1'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'about_id' => ['nullable', 'integer', 'exists:people,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $actor = $this->user();
            $recipientIds = collect($this->input('recipient_ids', []));

            if (! $actor) {
                return;
            }

            if ($recipientIds->isNotEmpty()) {
                $allowedCount = User::query()
                    ->whereIn('id', $recipientIds)
                    ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'owner'))
                    ->when(! $actor->hasRole('owner'), fn ($query) => $query->where('gym_id', $actor->gym_id))
                    ->count();

                if ($allowedCount !== $recipientIds->count()) {
                    $validator->errors()->add('recipient_ids', __('backend_messages.one_selected_recipients_unavailable'));
                }
            }

            if ($this->filled('about_id') && ! $actor->hasRole('owner')) {
                $personAvailable = Person::query()
                    ->whereKey($this->input('about_id'))
                    ->whereHas('gyms', fn ($query) => $query->where('gyms.id', $actor->gym_id))
                    ->exists();

                if (! $personAvailable) {
                    $validator->errors()->add('about_id', __('backend_messages.selected_customer_not_found'));
                }
            }
        });
    }
}
