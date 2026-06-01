<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage; 
class Gym extends Model
{
    use SoftDeletes;

    protected $guarded = [];



    protected static function booted(): void
    {
        static::deleting(function (Gym $gym) {

            if ($gym->logo && Storage::disk('public')->exists($gym->logo)) {

                Storage::disk('public')->delete($gym->logo);
            }
        });
    }
    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function partners()
    {
        return $this->hasMany(Partner::class);
    }

    public function entryCodes()
    {
        return $this->hasMany(EntryCode::class, 'gym_id');
    }
}
