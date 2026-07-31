<?php

namespace App\Http\Requests\MembershipSales;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMembershipSaleReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reminder_recipient_ids' => array_values(array_unique(array_filter(
                array_map('intval', (array) $this->input('reminder_recipient_ids', []))
            ))),
            'reminder_title' => $this->input('reminder_title') ?: null,
            'reminder_description' => $this->input('reminder_description') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'reminder_scheduled_at' => ['required', 'date', 'after:now'],
            'reminder_recipient_ids' => ['required', 'array', 'min:1'],
            'reminder_recipient_ids.*' => ['integer', 'exists:users,id'],
            'reminder_title' => ['nullable', 'string', 'max:255'],
            'reminder_description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $actor = $this->user();
            $recipientIds = $this->input('reminder_recipient_ids', []);

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
                $validator->errors()->add('reminder_recipient_ids', 'Ընտրված ստացողներից մեկը հասանելի չէ։');
            }
        });
    }
}
