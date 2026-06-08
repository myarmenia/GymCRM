<?php

namespace App\Models;

use App\Traits\FilterTrait;
use Dom\Attr;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;


class Person extends Model
{
    use HasFactory, SoftDeletes, FilterTrait;

    protected $guarded =[];
    protected $table = 'people';

    protected array $filterConfig = [
        'name' => [
            'method' => 'where',
            'operator' => 'like',
        ],
        'surname' => [
            'method' => 'where',
            'operator' => 'like',
        ],
        'full_name' => [
            'callback' => 'filterFullName',
        ],
        'phone' => [
            'method' => 'where',
            'operator' => 'like',
        ],
        'email' => [
            'method' => 'where',
            'operator' => 'like',
        ],
        'type' => [
            'method' => 'where',
        ],
        'birth_date_from' => [
            'column' => 'birth_date',
            'method' => 'whereDate',
            'operator' => '>=',
        ],
        'birth_date_to' => [
            'column' => 'birth_date',
            'method' => 'whereDate',
            'operator' => '<=',
        ],
        'created_at_from' => [
            'column' => 'created_at',
            'method' => 'whereDate',
            'operator' => '>=',
        ],
        'created_at_to' => [
            'column' => 'created_at',
            'method' => 'whereDate',
            'operator' => '<=',
        ],
    ];

    protected function filterFullName(Builder $query, mixed $value): void
    {
        $terms = preg_split('/\s+/', trim((string) $value)) ?: [];

        $query->where(function (Builder $q) use ($value, $terms) {
            $q->where('name', 'like', "%{$value}%")
                ->orWhere('surname', 'like', "%{$value}%");

            foreach ($terms as $term) {
                $q->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('surname', 'like', "%{$term}%");
            }
        });
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



    public function memberships()
    {
        return $this->hasMany(PersonMembership::class);
    }

    public function activeMemberships()
    {
        return $this->hasMany(PersonMembership::class)
            ->where('status', 'active');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'owner');
    }

    public function gyms()
    {
        return $this->belongsToMany(Gym::class, 'gym_person');

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
