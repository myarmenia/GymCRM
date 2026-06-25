<?php

namespace App\Services;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TableService
{
    protected function getModelClass(string $model): string
    {
        $class = 'App\\Models\\' . Str::studly(Str::singular($model));

        if (!class_exists($class)) {
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

        if (!array_key_exists($column, $item->getAttributes()) && !isset($item->$column)) {
            abort(400, "The column '{$column}' does not exist on this model.");
        }

        $item->$column = !$item->$column;
        $item->save();

        return (bool) $item->$column;
    }

    public function delete(string $model, $id): void
    {

        $item = $this->find($model, $id);
        $this->ensureCanUseGenericMutation($item);
        $item->delete();
    }

    private function ensureCanUseGenericMutation(Model $item): void
    {
        if ($item instanceof MembershipPlan && $item->is_locked) {
            abort(422, $item->lock_reason ?? 'Այս աբոնեմենտը հնարավոր չէ փոփոխել։');
        }
    }
}
