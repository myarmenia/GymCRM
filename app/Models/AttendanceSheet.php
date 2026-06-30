<?php

namespace App\Models;

use App\Helpers\MyHelper;
use App\Traits\ReportFilterTrait;
use App\Traits\ReportTrait;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSheet extends Model
{
    use HasFactory;

    protected $guarded =[];
    protected $table = 'attendance_sheets';
    // protected $filterFields = ['people_id','date'];
    protected $filterFieldsInRelation = ['name'];
    protected $appends = ['schedule_name_id', 'department_id'];

    public function relation()
    {
        return $this->morphTo();
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
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
