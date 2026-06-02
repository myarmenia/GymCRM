<?php

namespace App\Models;

use Dom\Attr;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;


class Person extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded =[];
    protected $table = 'people';

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class, 'gym_id');
    }


    public function entryPermissions()
    {
        return $this->morphMany(EntryPermission::class, 'relation');
    }

    public function attendance_sheets(): HasMany
    {
        return $this->hasMany(AttendanceSheet::class,'people_id');
    }

    public function attendanceSheets()
    {
        return $this->morphMany(AttendanceSheet::class, 'relation');
    }

    // public function activated_code_connected_person(): HasOne{
    //     return $this->hasOne(EntryPermission::class)->where('status',1);
    // }
    // public function superviced(){
    //     return $this->hasOne(Superviced::class,'people_id');
    // }
    // public function schedule_department_people(){

    //     return $this->hasMany(ScheduleDepartmentPerson::class);
    // }
    // public function absence(){

    //     return $this->hasMany(Absence::class);
    // }






}
