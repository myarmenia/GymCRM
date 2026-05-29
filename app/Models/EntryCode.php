<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntryCode extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded=[];
     public $timestamps = true;
    protected $table = "entry_codes";

    public function gym()
    {
        return $this->belongsTo(Gym::class,'gym_id');
    }

    // public function people(){
    //     return $this->belongsTo(Person::class,'people_id');
    // }
    // public function person_permission(): HasMany{
    //     return $this->hasMany(PersonPermission::class);
    // }
    // public function active_person(): HasOne{
    //     return $this->hasOne(PersonPermission::class)->where('status',1);
    // }

}
