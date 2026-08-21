<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasUuidAndVersion
{
    protected static function bootHasUuidAndVersion(): void
    {
        static::creating(function (Model $model): void {
            $model->uuid ??= (string) Str::uuid();
            $model->version ??= 1;
        });

        static::updating(function (Model $model): void {
            if (! $model->isDirty('version')) {
                $model->version = ((int) $model->getOriginal('version')) + 1;
            }
        });

        static::deleting(function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                $model->version = ((int) $model->getOriginal('version')) + 1;
                $model->saveQuietly();
            }
        });
    }
}
