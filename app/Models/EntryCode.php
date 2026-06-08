<?php

namespace App\Models;

use App\Traits\FilterTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntryCode extends Model
{
    use HasFactory, SoftDeletes, FilterTrait;

    protected $guarded = [];
    public $timestamps = true;
    protected $table = "entry_codes";

    protected array $filterConfig = [
        'type' => [
            'method' => 'where',
        ],
        'gym_id' => [
            'method' => 'where',
        ],
        'status' => [
            'method' => 'where',
        ],
    ];

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
