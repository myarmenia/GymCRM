<?php

namespace App\Models;

use App\Helpers\MyHelper;
use App\Traits\HasUuidAndVersion;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttendanceSheet extends Model
{
    use HasFactory;
    use HasUuidAndVersion;

    protected $guarded = [];

    protected $table = 'attendance_sheets';

    // protected $filterFields = ['people_id','date'];
    protected $filterFieldsInRelation = ['name'];

    protected $appends = ['schedule_name_id', 'department_id'];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'online' => 'boolean',
        ];
    }

    public function relation()
    {
        return $this->morphTo();
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function getOwnerTypeAttribute(): ?string
    {
        return match ($this->relation_type) {
            Person::class => 'person',
            User::class => 'user',
            default => null,
        };
    }

    public function getClientIdAttribute(): ?int
    {
        return $this->gym_id;
    }

    public function getOwnerIdAttribute(): ?int
    {
        return $this->relation_id;
    }

    public function getDetectedAtAttribute()
    {
        return $this->date;
    }

    public function getActionAttribute(): ?string
    {
        return $this->direction;
    }

    public function getStatusAttribute(): string
    {
        return 'success';
    }

    public function getReasonAttribute(): string
    {
        return 'success';
    }

    public function getAccessAllowedAttribute(): bool
    {
        return true;
    }

    public function personMemberships(): BelongsToMany
    {
        return $this->belongsToMany(PersonMembership::class, 'attendance_person_memberships')
            ->withTimestamps();
    }

    // accesors

    public function getScheduleNameIdAttribute()
    {
        return $this->relation && $this->relation->schedule_department_people?->isNotEmpty()
            ? $this->relation->schedule_department_people->first()->schedule_name_id
            : null;
    }

    public function getScheduleDetailsAttribute()
    {
        return $this->relation && $this->relation->schedule_department_people?->isNotEmpty()
            ? $this->relation->schedule_department_people->first()->schedule_name?->schedule_details
            : null;
    }

    public function getDepartmentIdAttribute()
    {
        return $this->relation && $this->relation->schedule_department_people?->isNotEmpty()
            ? $this->relation->schedule_department_people->first()->department_id
            : null;
    }

    public function scopeForClient(Builder $query, $month, $departmentId = null)
    {
        $clientId = MyHelper::find_auth_user_client();

        [$year, $month] = explode('-', $month);

        return $query
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereHas('relation', function ($q) use ($clientId, $departmentId) {
                $q->where('client_id', $clientId)
                    ->whereHas('schedule_department_people', function ($subQ) use ($clientId, $departmentId) {
                        $subQ->where('client_id', $clientId);

                        if ($departmentId) {
                            $subQ->where('department_id', $departmentId);
                        }
                    });
            })
            ->with(['relation.schedule_department_people']);
    }

    public static function forPersonOnDate($relationId, $date)
    {
        return self::where('relation_id', $relationId)
            ->whereDate('date', $date)
            ->get();
    }
}
