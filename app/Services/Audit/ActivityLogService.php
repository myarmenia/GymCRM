<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService
{
    public function log(
        string $action,
        string $message,
        Model $entity,
        ?array $changes = null,
        ?array $meta = null,
        ?int $gymId = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'gym_id' => $gymId ?? auth()->user()?->gym_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'loggable_type' => $entity::class,
            'loggable_id' => $entity->getKey(),
            'message' => $message,
            'changes' => $changes,
            'meta' => $meta,
        ]);
    }
}
