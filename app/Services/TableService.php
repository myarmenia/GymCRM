<?php

namespace App\Services;

use App\Models\Document;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\GymPerson;
use App\Models\InventoryProduct;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\Warehouse;
use App\Services\Audit\PersonAuditService;
use App\Services\Documents\DocumentGymAccess;
use App\Services\Documents\DocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TableService
{
    protected function getModelClass(string $model): string
    {
        $class = 'App\\Models\\'.Str::studly(Str::singular($model));

        if (! class_exists($class)) {
            abort(404, 'Model not found');
        }

        return $class;
    }

    public function find(string $model, $id): Model
    {
        $modelClass = $this->getModelClass($model);

        return $modelClass::findOrFail($id);
    }

    public function toggleChange(string $model, $id, string $column = 'active'): bool
    {
        $item = $this->find($model, $id);

        $this->ensureCanUseGenericMutation($item);

        if (! array_key_exists($column, $item->getAttributes()) && ! isset($item->$column)) {
            abort(400, "The column '{$column}' does not exist on this model.");
        }

        $item->$column = ! $item->$column;
        $item->save();

        return (bool) $item->$column;
    }

    public function delete(string $model, $id): void
    {
        $item = $this->find($model, $id);
        if ($item instanceof Document) {
            app(DocumentService::class)->deleteDocument($item);

            return;
        }
        if ($item instanceof Person) {
            $user = auth()->user();
            abort_unless($user?->hasAnyRole(['admin', 'super_admin', 'sales_manager']) && ! $user->hasRole('owner') && $user->gym_id, 403);
            DB::transaction(function () use ($item, $user): void {
                $link = GymPerson::where('person_id', $item->id)->where('gym_id', $user->gym_id)->lockForUpdate()->firstOrFail();
                $permissions = $item->entryPermissions()->whereHas('entryCode', fn ($query) => $query->where('gym_id', $user->gym_id));
                $codeIds = (clone $permissions)->pluck('entry_code_id');
                $permissions->delete();
                foreach ($codeIds as $codeId) {
                    if (! EntryPermission::where('entry_code_id', $codeId)->where('status', true)->whereNull('deleted_at')->exists()) {
                        EntryCode::whereKey($codeId)->update(['activation' => false]);
                    }
                }
                $snapshot = app(PersonAuditService::class)->snapshot($item->forGym((int) $user->gym_id));
                $link->delete();
                app(PersonAuditService::class)->afterDeleted($item, $snapshot);
            });

            return;
        }
        $this->ensureCanUseGenericMutation($item);
        $item->delete();
    }

    private function ensureCanUseGenericMutation(Model $item): void
    {
        $user = auth()->user();
        if ($item instanceof EntryPermission && $item->relation_type === Person::class) {
            abort(403, 'Use the customer card endpoints.');
        }
        if ($item instanceof Document) {
            app(DocumentGymAccess::class)->authorizeDocument($item);
        }
        if ($user?->hasRole('owner') && (array_key_exists('membership_sale_id', $item->getAttributes()) || array_key_exists('person_membership_id', $item->getAttributes()))) {
            abort(403);
        }

        if ($item instanceof Person || $item instanceof GymPerson) {
            abort(403, 'Manage customer gym state through the customer endpoints.');
        }
        if ($user?->hasRole('owner') && ($item instanceof MembershipSale || $item instanceof PersonMembership)) {
            abort(403);
        }

        if (
            $user?->hasRole('manager')
            && ! $user->hasAnyRole(['owner', 'admin', 'super_admin', 'sales_manager'])
            && ! ($item instanceof Warehouse)
            && ! ($item instanceof InventoryProduct)
        ) {
            abort(403, 'Managers can only modify warehouses and products.');
        }

        if ($item instanceof MembershipPlan && $item->is_locked) {
            abort(422, $item->lock_reason ?? 'This membership plan cannot be changed.');
        }
    }
}
