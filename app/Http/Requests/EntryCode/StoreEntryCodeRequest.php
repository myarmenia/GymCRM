<?php

namespace App\Http\Requests\EntryCode;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntryCodeRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'gym_id' => 'required|exists:gyms,id',
            'token'  => 'required|string|max:255|unique:entry_codes,token',
            'type'   => 'required|string|in:rfId,FaceId',
            'activation' => 'sometimes|boolean',
        ];
    }
}