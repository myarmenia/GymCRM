<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OwnerSale extends Model
{
    use HasUuidAndVersion, SoftDeletes;

    public const PAYMENT_TYPES = ['cash', 'transfer'];

    public const PAYMENT_STATUSES = ['paid', 'unpaid'];

    public const STATUSES = ['active', 'inactive', 'cancelled', 'archived'];

    protected $guarded = [];

    protected $appends = ['period_status', 'days_left'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'starts_at' => 'date:Y-m-d',
            'ends_at' => 'date:Y-m-d',
            'expiry_notified_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    protected function versionIgnoredAttributes(): array
    {
        return ['expiry_notified_at'];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPeriodStatusAttribute(): string
    {
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        $today = today(config('app.timezone'));

        if ($this->starts_at->startOfDay()->gt($today)) {
            return 'future';
        }

        if ($this->ends_at->startOfDay()->lt($today)) {
            return 'expired';
        }

        return $this->ends_at->startOfDay()->lte($today->copy()->addDays(3))
            ? 'ending'
            : 'active';
    }

    public function getDaysLeftAttribute(): int
    {
        return (int) max(today(config('app.timezone'))->diffInDays($this->ends_at->startOfDay(), false), 0);
    }
}
