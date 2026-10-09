<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;

class ContactNote extends Model
{
    use HasUuidAndVersion;

    public $timestamps = false;

    protected $fillable = ['user_id', 'phone_number', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
