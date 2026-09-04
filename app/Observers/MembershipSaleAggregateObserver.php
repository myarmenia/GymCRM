<?php

namespace App\Observers;

use App\Models\Guest;
use App\Models\HdmCashier;
use App\Models\HdmConfig;
use App\Models\HdmOperation;
use App\Models\HdmOperationPayment;
use App\Models\MembershipPlanPayment;
use App\Models\MembershipSale;
use App\Models\MembershipSaleDiscount;
use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use App\Models\SalespersonCommission;
use App\Models\TrainerCommission;
use Illuminate\Database\Eloquent\Model;

class MembershipSaleAggregateObserver
{
    public function created(Model $model): void
    {
        $this->touchRelatedSales($model);
    }

    public function updated(Model $model): void
    {
        $this->touchRelatedSales($model);
    }

    public function deleting(Model $model): void
    {
        if ($model instanceof HdmConfig || $model instanceof HdmCashier) {
            $model->setRelation('__membershipSaleSyncIds', $this->saleIds($model));
        }
    }

    public function deleted(Model $model): void
    {
        if ($model->relationLoaded('__membershipSaleSyncIds')) {
            $this->touchSaleIds(
                $model,
                (array) $model->getRelation('__membershipSaleSyncIds'),
            );

            return;
        }

        $this->touchRelatedSales($model);
    }

    public function restored(Model $model): void
    {
        $this->touchRelatedSales($model);
    }

    private function touchRelatedSales(Model $model): void
    {
        $this->touchSaleIds($model, $this->saleIds($model));
    }

    /** @param list<int> $saleIds */
    private function touchSaleIds(Model $model, array $saleIds): void
    {
        foreach ($saleIds as $saleId) {
            $sale = (new MembershipSale)
                ->setConnection($model->getConnectionName())
                ->newQuery()
                ->withTrashed()
                ->find($saleId);

            if ($sale === null) {
                continue;
            }

            $sale->forceFill(['version' => (int) $sale->version + 1]);
            $sale->save();
        }
    }

    /** @return list<int> */
    private function saleIds(Model $model): array
    {
        if ($model instanceof MembershipPlanPayment
            || $model instanceof MembershipSaleDiscount
            || $model instanceof PersonMembership
            || $model instanceof TrainerCommission
            || $model instanceof SalespersonCommission) {
            return $model->membership_sale_id ? [(int) $model->membership_sale_id] : [];
        }

        if ($model instanceof PersonMembershipFreeze || $model instanceof Guest) {
            $saleId = $this->connectionModel(PersonMembership::class, $model)
                ->newQuery()
                ->whereKey($model->person_membership_id)
                ->value('membership_sale_id');

            return $saleId === null ? [] : [(int) $saleId];
        }

        if ($model instanceof HdmOperation) {
            return $this->saleIdsForOperation($model);
        }

        if ($model instanceof HdmOperationPayment) {
            $operation = $this->connectionModel(HdmOperation::class, $model)
                ->newQuery()
                ->find($model->hdm_operation_id);

            return $operation === null ? [] : $this->saleIdsForOperation($operation);
        }

        if ($model instanceof HdmConfig) {
            return $this->saleIdsForHdmReference($model, 'hdm_config_id');
        }

        if ($model instanceof HdmCashier) {
            return $this->saleIdsForHdmReference($model, 'hdm_cashier_id');
        }

        return [];
    }

    /** @return list<int> */
    private function saleIdsForOperation(HdmOperation $operation): array
    {
        if ($operation->operationable_type !== (new MembershipPlanPayment)->getMorphClass()) {
            return [];
        }

        $saleId = $this->connectionModel(MembershipPlanPayment::class, $operation)
            ->newQuery()
            ->withTrashed()
            ->whereKey($operation->operationable_id)
            ->value('membership_sale_id');

        return $saleId === null ? [] : [(int) $saleId];
    }

    /** @return list<int> */
    private function saleIdsForHdmReference(Model $model, string $column): array
    {
        $query = $this->connectionModel(HdmOperation::class, $model)->newQuery();

        if ($model instanceof HdmConfig) {
            $cashierIds = $this->connectionModel(HdmCashier::class, $model)
                ->newQuery()
                ->where('hdm_config_id', $model->getKey())
                ->pluck('id');
            $query->where(function ($nested) use ($column, $model, $cashierIds): void {
                $nested->where($column, $model->getKey());
                if (! $cashierIds->isEmpty()) {
                    $nested->orWhereIn('hdm_cashier_id', $cashierIds);
                }
            });
        } else {
            $query->where($column, $model->getKey());
        }

        return $query
            ->where('operationable_type', (new MembershipPlanPayment)->getMorphClass())
            ->join(
                'membership_plan_payments',
                'membership_plan_payments.id',
                '=',
                'hdm_operations.operationable_id',
            )
            ->distinct()
            ->pluck('membership_plan_payments.membership_sale_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @template TModel of Model @param class-string<TModel> $class @return TModel */
    private function connectionModel(string $class, Model $source): Model
    {
        return (new $class)->setConnection($source->getConnectionName());
    }
}
