<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class DocumentGymAccess
{
    public function gymFor(Model $owner, ?int $requestedGym = null): int
    {
        if (request()->has('gym_id')) {
            request()->validate(['gym_id' => ['nullable', 'integer', 'min:1']]);
        }
        $viewer = auth()->user();
        abort_unless($viewer instanceof User, 403);
        abort_unless($viewer->hasAnyRole(['owner', 'manager', 'sales_manager', 'super_admin', 'admin']), 403);
        $gymId = $viewer->hasRole('owner') ? ($requestedGym ?? ($owner instanceof Person ? null : (int) $owner->gym_id)) : (int) $viewer->gym_id;
        if (! $gymId) {
            throw ValidationException::withMessages(['gym_id' => __('validation.required', ['attribute' => 'gym'])]);
        }
        if (! $viewer->hasRole('owner') && $requestedGym !== null) {
            abort_unless((int) $requestedGym === (int) $gymId, 403);
        }
        if ($owner instanceof Person) {
            abort_unless($owner->gyms()->where('gyms.id', $gymId)->exists(), 403);
        } else {
            abort_unless((int) $owner->gym_id === (int) $gymId, 403);
        }

        return (int) $gymId;
    }

    public function authorizeDocument(Document $document): void
    {
        abort_unless($document->owner && $document->gym_id, 403);
        $this->gymFor($document->owner, (int) $document->gym_id);
    }
}
