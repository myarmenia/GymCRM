<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntryCode extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];
    public $timestamps = true;
    protected $table = "entry_codes";

    public function gym()
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }


    public function entryPermissions()
    {
        return $this->hasMany(EntryPermission::class);
    }

    public function getUsersAttribute()
    {
        return $this->entryPermissions()
            ->with('relation')
            ->get()
            ->pluck('relation')
            ->filter(fn($item) => $item instanceof User);
    }

    public function getPeopleAttribute()
    {
        return $this->entryPermissions()
            ->with('relation')
            ->get()
            ->pluck('relation')
            ->filter(fn($item) => $item instanceof Person);
    }
}
