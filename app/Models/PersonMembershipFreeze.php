<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PersonMembershipFreeze extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'cancel_effective_date' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
        ];
    }

    public function scopeEffectiveOn(Builder $query, Carbon|string $date): Builder
    {
        $date = Carbon::parse($date)->toDateString();

        return $query
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('cancel_effective_date')
                    ->orWhereDate('cancel_effective_date', '>', $date);
            });
    }

    public function coversDate(Carbon|string $date): bool
    {
        $date = Carbon::parse($date)->startOfDay();
        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date)->startOfDay();

        if (! $date->betweenIncluded($start, $end)) {
            return false;
        }

        return ! $this->cancel_effective_date
            || $date->lt(Carbon::parse($this->cancel_effective_date)->startOfDay());
    }

    public function isCancellableOn(Carbon|string $date): bool
    {
        return ! $this->cancelled_at
            && Carbon::parse($this->end_date)->startOfDay()->gte(Carbon::parse($date)->startOfDay());
    }

    public function personMembership()
    {
        return $this->belongsTo(PersonMembership::class);
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
