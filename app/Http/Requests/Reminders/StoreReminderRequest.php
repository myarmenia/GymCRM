<?php

namespace App\Http\Requests\Reminders;

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
            $recipientIds = $this->input('recipient_ids', []);

            if (! $actor || empty($recipientIds)) {
                return;
            }

            $invalidRecipients = User::query()
                ->whereIn('id', $recipientIds)
                ->where(function ($query) use ($actor) {
                    $query
                        ->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'owner'))
                        ->when(! $actor->hasRole('owner'), fn ($userQuery) => $userQuery->orWhere('gym_id', '!=', $actor->gym_id));
                })
                ->exists();

            if ($invalidRecipients) {
                $validator->errors()->add('recipient_ids', 'Ընտրված ստացողներից մեկը հասանելի չէ։');
            }
        });
    }
}
