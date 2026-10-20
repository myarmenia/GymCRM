<?php

namespace App\Http\Requests\Notifications;

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $sendToAll = filter_var($this->input('send_to_all'), FILTER_VALIDATE_BOOLEAN);

        $this->merge([
            'send_to_all' => $sendToAll ? 1 : 0,
            'recipient_ids' => $sendToAll ? [] : (array) $this->input('recipient_ids', []),
            'about_id' => $this->input('about_id') ?: null,
            'title' => $this->input('title') ?: null,
            'description' => $this->input('description') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'send_to_all' => ['required', 'boolean'],
            'recipient_ids' => ['exclude_if:send_to_all,1', 'required', 'array', 'min:1'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'about_id' => ['nullable', 'integer', 'exists:people,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $sender = $this->user();

            if (! $sender) {
                return;
            }

            if (! $this->boolean('send_to_all')) {
                $recipientIds = collect($this->input('recipient_ids', []))
                    ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
                    ->map(fn ($id) => (int) $id)
                    ->unique();

                if ($recipientIds->isNotEmpty()) {
                    $allowedCount = User::query()
                        ->whereIn('id', $recipientIds)
                        ->whereKeyNot($sender->id)
                        ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'owner'))
                        ->when(! $sender->hasRole('owner'), fn ($query) => $query->where('gym_id', $sender->gym_id))
                        ->count();

                    if ($allowedCount !== $recipientIds->count()) {
                        $validator->errors()->add('recipient_ids', __('backend_messages.one_selected_recipients_unavailable'));
                    }
                }
            }

            if ($this->filled('about_id') && ! $sender->hasRole('owner')) {
                $personAvailable = Person::query()
                    ->whereKey($this->input('about_id'))
                    ->whereHas('gyms', fn ($query) => $query->where('gyms.id', $sender->gym_id))
                    ->exists();

                if (! $personAvailable) {
                    $validator->errors()->add('about_id', __('backend_messages.selected_customer_not_found'));
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'send_to_all.required' => __('backend_messages.delivery_type_required'),
            'send_to_all.boolean' => __('backend_messages.delivery_type_invalid'),
            'recipient_ids.required' => __('backend_messages.select_least_one_recipient'),
            'recipient_ids.array' => __('backend_messages.recipient_data_invalid'),
            'recipient_ids.min' => __('backend_messages.select_least_one_recipient'),
            'recipient_ids.*.exists' => __('backend_messages.one_selected_recipients_not_found'),
            'about_id.exists' => __('backend_messages.selected_customer_not_found'),
            'title.required' => __('backend_messages.title_required'),
            'title.max' => __('backend_messages.title_too_long'),
            'description.required' => __('backend_messages.description_required'),
        ];
    }
}
