<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function category()
    {
        return $this->belongsTo(ReminderCategory::class, 'category_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function about()
    {
        return $this->belongsTo(Person::class, 'about_id');
    }

    public function recipients()
    {
        return $this->belongsToMany(User::class, 'reminder_recipients')
            ->withPivot(['status', 'sent_at', 'error_message'])
            ->withTimestamps();
    }
}
