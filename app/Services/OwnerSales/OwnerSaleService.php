<?php

namespace App\Services\OwnerSales;

use App\Interfaces\OwnerSales\OwnerSaleInterface;
use App\Models\OwnerSale;
use App\Models\User;
use App\Services\Audit\AuditManager;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\DB;

class OwnerSaleService
{
    public function __construct(
        private readonly OwnerSaleInterface $ownerSales,
        private readonly AuditManager $auditManager,
        private readonly NotificationService $notifications,
    ) {}

    public function paginate(array $filters = [], int $perPage = 10)
    {
        return $this->ownerSales->paginateForOwner($filters, $perPage);
    }

    public function summary(array $filters = []): array
    {
        return $this->ownerSales->summaryForOwner($filters);
    }

    public function find(int $id): OwnerSale
    {
        /** @var OwnerSale $sale */
        $sale = $this->ownerSales->findOrFail($id, ['gym', 'creator']);

        return $sale;
    }

    public function create(array $data, User $actor): OwnerSale
    {
        return DB::transaction(function () use ($data, $actor): OwnerSale {
            $data['created_by'] = $actor->id;

            /** @var OwnerSale $sale */
            $sale = $this->ownerSales->create($data);
            $this->auditManager->created(
                entity: $sale,
                action: 'owner_sale.created',
                snapshot: $this->snapshot($sale),
                gymId: $sale->gym_id,
            );

            return $sale->load(['gym', 'creator']);
        });
    }

    public function update(int $id, array $data, User $actor): OwnerSale
    {
        return DB::transaction(function () use ($id, $data, $actor): OwnerSale {
            $sale = $this->find($id);
            $oldSnapshot = $this->snapshot($sale);
            if (array_key_exists('ends_at', $data) && $sale->ends_at->toDateString() !== $data['ends_at']) {
                $data['expiry_notified_at'] = null;
            }

            $data['version'] = (int) $sale->version + 1;
            /** @var OwnerSale $sale */
            $sale = $this->ownerSales->update($id, $data);

            $this->auditManager->updated(
                entity: $sale,
                action: 'owner_sale.updated',
                oldSnapshot: $oldSnapshot,
                newSnapshot: $this->snapshot($sale),
                gymId: $sale->gym_id,
            );

            return $sale->load(['gym', 'creator']);
        });
    }

    public function cancel(int $id, User $actor): OwnerSale
    {
        return DB::transaction(function () use ($id, $actor): OwnerSale {
            $sale = $this->find($id);
            $oldSnapshot = $this->snapshot($sale);
            $sale->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'version' => (int) $sale->version + 1,
            ]);
            $this->auditManager->updated(
                entity: $sale,
                action: 'owner_sale.cancelled',
                oldSnapshot: $oldSnapshot,
                newSnapshot: $this->snapshot($sale),
                gymId: $sale->gym_id,
            );

            return $sale;
        });
    }

    public function delete(int $id, User $actor): void
    {
        DB::transaction(function () use ($id, $actor): void {
            $sale = $this->find($id);
            $this->auditManager->deleted(
                entity: $sale,
                action: 'owner_sale.deleted',
                oldSnapshot: $this->snapshot($sale),
                gymId: $sale->gym_id,
            );
            $this->ownerSales->delete($id);
        });
    }

    public function archive(int $id, User $actor): OwnerSale
    {
        return DB::transaction(function () use ($id, $actor): OwnerSale {
            $sale = $this->find($id);
            $oldSnapshot = $this->snapshot($sale);
            $sale->update([
                'status' => 'archived',
                'version' => (int) $sale->version + 1,
            ]);
            $this->auditManager->updated(
                entity: $sale,
                action: 'owner_sale.archived',
                oldSnapshot: $oldSnapshot,
                newSnapshot: $this->snapshot($sale),
                gymId: $sale->gym_id,
            );

            return $sale;
        });
    }

    public function gymHasAccess(int $gymId): bool
    {
        return ! $this->ownerSales->gymHasSales($gymId)
            || $this->ownerSales->gymHasActivePeriod($gymId);
    }

    public function sendExpiryNotifications(): int
    {
        $owners = User::role('owner')->get();
        $sender = $owners->first();
        if (! $sender || $owners->isEmpty()) {
            return 0;
        }

        $sent = 0;
        foreach ($this->ownerSales->endingSoon() as $sale) {
            $sent += $this->notifications->createForRecipientIds($sender, [
                'title' => __('owner_sales.notification_title'),
                'description' => __('owner_sales.notification_description', [
                    'gym' => $sale->gym?->name ?? "#{$sale->gym_id}",
                    'date' => $sale->ends_at->format('d.m.Y'),
                ]),
            ], $owners->pluck('id'));

            $sale->update(['expiry_notified_at' => now()]);
        }

        return $sent;
    }

    private function snapshot(OwnerSale $sale): array
    {
        return [
            'id' => $sale->id,
            'gym_id' => $sale->gym_id,
            'amount' => (float) $sale->amount,
            'payment_type' => $sale->payment_type,
            'payment_status' => $sale->payment_status,
            'starts_at' => $sale->starts_at->toDateString(),
            'ends_at' => $sale->ends_at->toDateString(),
            'notes' => $sale->notes,
            'status' => $sale->status,
        ];
    }
}
