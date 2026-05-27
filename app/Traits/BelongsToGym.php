<?php

namespace App\Traits;

use App\Models\Gym;

trait BelongsToGym
{
    protected static function bootBelongsToGym()
    {
        static::creating(function ($model) {
            if (!$model->gym_id) {
                $model->gym_id = auth()->user()?->gym_id;
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('gym_id')) {
                $model->gym_id = $model->getOriginal('gym_id');
            }
        });

    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function isSameGym(): bool
    {
        return $this->gym_id === auth()->user()?->gym_id;
    }
}
