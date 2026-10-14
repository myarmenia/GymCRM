<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class ContactProfile extends Model
{
    use HasUuidAndVersion;

    protected $fillable = [
        'uuid', 'gym_id', 'user_id', 'group_uuid', 'is_primary',
        'phone_number', 'phone_normalized', 'name', 'surname', 'email',
        'birth_date', 'gender', 'relationship',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'birth_date' => 'date:Y-m-d'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function members()
    {
        return $this->hasMany(self::class, 'group_uuid', 'group_uuid')->orderByDesc('is_primary')->orderBy('id');
    }

    public function notes()
    {
        return $this->hasMany(ContactNote::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function primary()
    {
        return $this->belongsTo(self::class, 'group_uuid', 'uuid');
    }
}
