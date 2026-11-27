<?php

namespace App\Repositories\Finance;

use App\Models\MembershipPlanPayment;
use App\Models\MembershipSale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class DailyCashboxRepository
{
    public function receivedPayments(User $actor, CarbonImmutable $day): Collection
    {
        return MembershipPlanPayment::query()
            ->join('financial_transactions as ledger', function ($join) {
                $join->on('ledger.source_id', '=', 'membership_plan_payments.id')
                    ->where('ledger.source_type', 'membership_plan_payment');
            })
            ->where('ledger.created_by', $actor->id)
            ->where('ledger.gym_id', $actor->gym_id)
            ->where('ledger.direction', 'income')
            ->where('ledger.status', 'posted')
            ->where('ledger.occurred_at', '>=', $day)
            ->where('ledger.occurred_at', '<', $day->addDay())
            ->where('ledger.amount', '>', 0)
            ->where('membership_plan_payments.type', 'payment')
            ->where('membership_plan_payments.status', 'paid')
            ->whereHas('membershipSale', fn ($query) => $query->withTrashed()->where('gym_id', $actor->gym_id))
            ->with([
                'membershipSale' => fn ($query) => $query->withTrashed(),
                'membershipSale.person' => fn ($query) => $query->withTrashed(),
                'membershipSale.membershipPlan' => fn ($query) => $query->withTrashed()->with('translations'),
                'paymentMethod.translations',
                'cardType',
            ])
            ->select([
                'membership_plan_payments.id',
                'membership_plan_payments.membership_sale_id',
                'membership_plan_payments.payment_method_id',
                'membership_plan_payments.card_type_id',
                'ledger.id as transaction_id',
                'ledger.amount as received_amount',
                'ledger.occurred_at as received_at',
            ])
            ->get();
    }

    public function salesWithoutReceivedPayment(User $actor, CarbonImmutable $day, array $receivedSaleIds): Collection
    {
        return MembershipSale::query()
            ->where('user_id', $actor->id)
            ->where('gym_id', $actor->gym_id)
            ->where('sold_at', '>=', $day)
            ->where('sold_at', '<', $day->addDay())
            ->whereNotIn('id', $receivedSaleIds)
            ->whereNotIn('payment_status', ['cancelled', 'refunded'])
            ->with([
                'person' => fn ($query) => $query->withTrashed(),
                'membershipPlan' => fn ($query) => $query->withTrashed()->with('translations'),
            ])
            ->get();
    }
}
