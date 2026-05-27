<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ModelService
{
    protected function getModelClass(string $model): string
    {
        // users → User
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


}
