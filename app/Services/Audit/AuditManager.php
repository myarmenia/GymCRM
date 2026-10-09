<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class AuditManager
{
    public function __construct(
        protected ActivityLogService $activityLogService,
    ) {}

    public function created(
        Model $entity,
        string $action,
        array $snapshot,
        ?string $message = null,
        ?int $gymId = null,
    ): ActivityLog {
        return $this->activityLogService->log(
            action: $action,
            message: $message ?? sprintf('%s #%s created', class_basename($entity), $entity->getKey()),
            entity: $entity,
            changes: [],
            meta: ['snapshot' => $snapshot],
            gymId: $gymId,
        );
    }

    public function updated(
        Model $entity,
        string $action,
        array $oldSnapshot,
        array $newSnapshot,
        ?string $message = null,
        ?int $gymId = null,
    ): ?ActivityLog {
        $changes = [];

        foreach (array_unique([...array_keys($oldSnapshot), ...array_keys($newSnapshot)]) as $field) {
            $oldValue = $oldSnapshot[$field] ?? null;
            $newValue = $newSnapshot[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[] = [
                    'field' => $field,
                    'type' => 'updated',
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        if ($changes === []) {
            return null;
        }

        return $this->activityLogService->log(
            action: $action,
            message: $message ?? sprintf('%s #%s updated', class_basename($entity), $entity->getKey()),
            entity: $entity,
            changes: $changes,
            meta: ['old_snapshot' => $oldSnapshot, 'snapshot' => $newSnapshot],
            gymId: $gymId,
        );
    }

    public function deleted(
        Model $entity,
        string $action,
        array $oldSnapshot,
        ?string $message = null,
        ?int $gymId = null,
    ): ActivityLog {
        return $this->activityLogService->log(
            action: $action,
            message: $message ?? sprintf('%s #%s deleted', class_basename($entity), $entity->getKey()),
            entity: $entity,
            changes: [[
                'field' => class_basename($entity),
                'type' => 'deleted',
                'old' => $oldSnapshot,
                'new' => null,
            ]],
            meta: ['old_snapshot' => $oldSnapshot],
            gymId: $gymId,
        );
    }
}
