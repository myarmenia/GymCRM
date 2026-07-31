<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderCategory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class, 'category_id');
    }
}
