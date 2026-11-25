<?php

namespace App\Services\Audit;

use App\Models\Person;
use App\Services\Audit\Snapshots\PersonSnapshotFactory;

class PersonAuditService
{
    public function __construct(
        protected AuditManager $auditManager,
        protected PersonSnapshotFactory $snapshotFactory,
    ) {}

    public function afterCreated(Person $person): void
    {
        $person->loadMissing('gyms:id,name');

        $this->auditManager->created(
            entity: $person,
            action: 'person.created',
            snapshot: $this->snapshotFactory->make($person),
            message: "Person #{$person->id} created",
            gymId: auth()->user()?->gym_id,
        );
    }

    public function snapshot(Person $person): array
    {
        return $this->snapshotFactory->make($person);
    }

    public function afterChanged(Person $person, array $oldSnapshot): void
    {
        $this->auditManager->updated(
            entity: $person,
            action: 'person.updated',
            oldSnapshot: $oldSnapshot,
            newSnapshot: $this->snapshotFactory->make($person),
            message: "Person #{$person->id} updated",
            gymId: auth()->user()?->gym_id,
        );
    }

    public function afterDeleted(Person $person, array $oldSnapshot): void
    {
        $this->auditManager->deleted(
            entity: $person, action: 'person.deleted', oldSnapshot: $oldSnapshot,
            message: "Person #{$person->id} removed from gym", gymId: auth()->user()?->gym_id,
        );
    }
}
