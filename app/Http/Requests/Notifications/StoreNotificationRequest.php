<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

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
        ]);
    }

    public function rules(): array
    {
        return [
            'send_to_all' => ['required', 'boolean'],
            'recipient_ids' => ['exclude_if:send_to_all,1', 'required', 'array', 'min:1'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'about_id' => ['nullable', 'integer', 'exists:people,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'send_to_all.required' => 'Ուղարկման տեսակը պարտադիր է։',
            'send_to_all.boolean' => 'Ուղարկման տեսակը սխալ է։',
            'recipient_ids.required' => 'Ընտրեք առնվազն մեկ ստացող։',
            'recipient_ids.array' => 'Ստացողների տվյալները սխալ են։',
            'recipient_ids.min' => 'Ընտրեք առնվազն մեկ ստացող։',
            'recipient_ids.*.exists' => 'Ընտրված ստացողներից մեկը չի գտնվել։',
            'about_id.exists' => 'Ընտրված հաճախորդը չի գտնվել։',
            'title.required' => 'Վերնագիրը պարտադիր է։',
            'title.max' => 'Վերնագիրը չափազանց երկար է։',
            'description.required' => 'Նկարագրությունը պարտադիր է։',
        ];
    }
}
