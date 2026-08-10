<?php

namespace App\Repositories\MobileMemberships;

use App\Interfaces\MobileMemberships\MobileMembershipInterface;
use App\Models\Person;
use App\Models\PersonMembership;
use Illuminate\Support\Collection;

class MobileMembershipRepository implements MobileMembershipInterface
{
    public function allForPerson(Person $person): Collection
    {
        return PersonMembership::query()
            ->with([
                'gym:id,name',
                'trainer:id,name,surname',
                'membershipSale:id,total_price,final_price',
                'membershipPlan.MembershipCategory.translations',
                'membershipPlan.translations',
            ])
            ->where('person_id', $person->id)
            ->latest('id')
            ->get();
    }

    public function findForPerson(Person $person, int $membershipId): ?PersonMembership
    {
        return PersonMembership::query()
            ->with([
                'gym:id,name,address,phone,email',
                'trainer:id,name,surname',
                'membershipPlan.MembershipCategory.translations',
                'membershipPlan.translations',
                'membershipSale.payments' => fn ($query) => $query
                    ->with(['paymentMethod.translations', 'cardType'])
                    ->latest('created_at'),
                'freezes' => fn ($query) => $query->latest('start_date'),
                'guests' => fn ($query) => $query
                    ->with('guest:id,name,surname')
                    ->latest('created_at'),
            ])
            ->where('person_id', $person->id)
            ->find($membershipId);
    }
}
