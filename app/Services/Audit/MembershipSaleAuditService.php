<?php

namespace App\Services\Audit;

use App\Models\MembershipSale;
use App\Services\Audit\Snapshots\MembershipSaleSnapshotFactory;

class MembershipSaleAuditService
{
    public function __construct(
        protected AuditManager $auditManager,
        protected MembershipSaleSnapshotFactory $snapshotFactory,
    ) {}

    public function afterCreated(MembershipSale $sale): void
    {
        $this->auditManager->created(
            entity: $sale,
            action: 'membership_sale.created',
            snapshot: $this->snapshotFactory->make($sale),
            message: "Membership sale #{$sale->id} created",
            gymId: (int) $sale->gym_id,
        );
    }

    public function snapshot(MembershipSale $sale): array
    {
        return $this->snapshotFactory->make($sale);
    }

    public function afterChanged(MembershipSale $sale, array $oldSnapshot, string $action, string $message): void
    {
        $this->auditManager->updated(
            entity: $sale,
            action: $action,
            oldSnapshot: $oldSnapshot,
            newSnapshot: $this->snapshotFactory->make($sale),
            message: $message,
            gymId: (int) $sale->gym_id,
        );
    }
}
