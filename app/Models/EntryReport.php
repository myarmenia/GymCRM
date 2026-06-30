<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryReport extends Model
{
    protected $fillable = [
        'client_id',
        'entry_code',
        'owner_type',
        'owner_id',
        'action',
        'status',
        'reason',
        'access_allowed',
        'mac',
        'device_time',
        'detected_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'access_allowed' => 'boolean',
            'device_time' => 'datetime',
            'detected_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'client_id');
    }

    public function getOwnerAttribute(): User|Person|null
    {
        if ($this->owner_type === 'user') {
            return User::find($this->owner_id);
        }

        if ($this->owner_type === 'person') {
            return Person::find($this->owner_id);
        }

        return null;
    }

    public function getOwnerNameAttribute(): ?string
    {
        $owner = $this->owner;

        return $owner
            ? trim(($owner->name ?? '') . ' ' . ($owner->surname ?? ''))
            : null;
    }

    public function getOwnerEmailAttribute(): ?string
    {
        return $this->owner?->email;
    }

    public function getOwnerPhoneAttribute(): ?string
    {
        return $this->owner?->phone;
    }

    public function getOwnerImageAttribute(): ?string
    {
        return $this->owner?->image;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'success' => 'Հաջողված',
            'denied' => 'Մերժված',
            default => 'Անհայտ',
        };
    }

    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'entry' => 'Մուտք',
            'exit' => 'Ելք',
            default => 'Անհայտ',
        };
    }

    public function getReasonLabelAttribute(): string
    {
        return match ($this->reason) {
            'success' => 'Հաջողված',
            'invalid_mac' => 'Սխալ սարք / MAC',
            'invalid_entry_code' => 'Սխալ մուտքի կոդ',
            'subscription_expired' => 'Աբոնեմենտի ժամկետը լրացել է',
            'no_active_subscription' => 'Ակտիվ աբոնեմենտ չկա',
            'owner_not_found' => 'Օգտատերը/հաճախորդը չի գտնվել',
            'client_mismatch' => 'Կոդը չի պատկանում տվյալ մասնաճյուղին',
            default => 'Անհայտ պատճառ',
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return $this->status === 'success' ? 'bg-label-success' : 'bg-label-danger';
    }

    public function getActionBadgeClassAttribute(): string
    {
        return match ($this->action) {
            'entry' => 'bg-label-primary',
            'exit' => 'bg-label-info',
            default => 'bg-label-secondary',
        };
    }

    public function getReasonBadgeClassAttribute(): string
    {
        return match ($this->reason) {
            'success' => 'bg-label-success',
            'subscription_expired', 'no_active_subscription' => 'bg-label-warning',
            'invalid_mac', 'invalid_entry_code', 'owner_not_found', 'client_mismatch' => 'bg-label-danger',
            default => 'bg-label-secondary',
        };
    }
}
