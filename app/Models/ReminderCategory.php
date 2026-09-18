<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ReminderCategory extends Model
{
    use HasUuidAndVersion;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (?string $value) => match ($this->slug) {
            'general' => __('backend_messages.general_reminder'),
            'membership_payment_due' => __('backend_messages.membership_payment_due'),
            default => $value,
        });
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class, 'category_id');
    }
}
