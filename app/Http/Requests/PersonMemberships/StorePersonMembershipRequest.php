<?php

namespace App\Http\Requests\PersonMemberships;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'membership_sale_id' => ['required', 'integer', 'exists:membership_sales,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'membership_plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(['waiting', 'active', 'frozen', 'expired', 'deleted', 'cancelled'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'valid_at' => ['nullable', 'date', 'after_or_equal:start_date'],
            'visits_used' => ['nullable', 'integer', 'min:0'],
            'visits_left' => ['nullable', 'integer', 'min:0'],
            'freeze_used' => ['nullable', 'integer', 'min:0'],
            'guest_used' => ['nullable', 'integer', 'min:0'],
            'freeze_left' => ['nullable', 'integer', 'min:0'],
            'guest_left' => ['nullable', 'integer', 'min:0'],
            'next_membership_id' => ['nullable', 'integer', 'exists:person_memberships,id'],
            'activated_at' => ['nullable', 'date'],
            'expired_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute դաշտը պարտադիր է։',
            'integer' => ':attribute դաշտը պետք է լինի ամբողջ թիվ։',
            'numeric' => ':attribute դաշտը պետք է լինի թիվ։',
            'min.numeric' => ':attribute դաշտը պետք է լինի առնվազն :min։',
            'date' => ':attribute դաշտը պետք է լինի վավեր ամսաթիվ։',
            'after_or_equal' => ':attribute-ը պետք է լինի :date-ից ոչ շուտ։',
            'exists' => 'Ընտրված :attribute-ը անվավեր է։',
            'in' => 'Ընտրված :attribute-ը անվավեր է։',
        ];
    }

    public function attributes(): array
    {
        return [
            'membership_sale_id' => 'աբոնեմենտի վաճառք',
            'user_id' => 'օգտատեր',
            'person_id' => 'հաճախորդ',
            'gym_id' => 'մարզասրահ',
            'membership_plan_id' => 'աբոնեմենտ',
            'trainer_id' => 'մարզիչ',
            'status' => 'կարգավիճակ',
            'start_date' => 'սկիզբ',
            'end_date' => 'ավարտ',
            'valid_at' => 'վավերական է մինչև',
            'visits_used' => 'օգտագործված այցելություններ',
            'visits_left' => 'մնացած այցելություններ',
            'freeze_used' => 'սառեցման քանակ',
            'guest_used' => 'հյուրերի քանակ',
            'freeze_left' => 'մնացած սառեցումներ',
            'guest_left' => 'մնացած հյուրեր',
            'next_membership_id' => 'հաջորդ աբոնեմենտ',
            'activated_at' => 'ակտիվացման ամսաթիվ',
            'expired_at' => 'ավարտման ամսաթիվ',
        ];
    }
}
