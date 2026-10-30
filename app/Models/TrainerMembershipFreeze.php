<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrainerMembershipFreeze extends Model
{
    use \App\Traits\HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'cancel_effective_date' => 'date:Y-m-d',
        ];
    }

    public function scopeEffectiveOn(Builder $query, Carbon|string $date): Builder
    {
        $date = Carbon::parse($date)->toDateString();

        return $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(fn (Builder $query) => $query->whereNull('cancel_effective_date')
                ->orWhereDate('cancel_effective_date', '>', $date));
    }

    public function trainerFreeze()
    {
        return $this->belongsTo(TrainerFreeze::class);
    }

    public function coversDate(Carbon|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();

        return $date->betweenIncluded($this->start_date, $this->end_date)
            && (! $this->cancel_effective_date || $date->lt($this->cancel_effective_date));
    }
}
