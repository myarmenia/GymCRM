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
            gymId: $person->gyms->first()?->id ?? auth()->user()?->gym_id,
        );
    }
}
