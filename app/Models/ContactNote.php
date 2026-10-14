<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class ContactNote extends Model
{
    use HasUuidAndVersion;

    public $timestamps = false;

    protected $fillable = ['user_id', 'phone_number', 'note', 'contact_profile_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function profile()
    {
        return $this->belongsTo(ContactProfile::class, 'contact_profile_id');
    }
}
