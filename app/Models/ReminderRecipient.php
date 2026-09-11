<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ReminderRecipient extends Pivot
{
    use HasUuidAndVersion;

    public $incrementing = true;

    protected $table = 'reminder_recipients';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
