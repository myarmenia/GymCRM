<?php

namespace App\Repositories\AttendanceSheets;

use App\Interfaces\AttendanceSheets\AttendanceSheetInterface;
use App\Models\AttendanceSheet;
use App\Repositories\BaseRepository;

class AttendanceSheetsRepository extends BaseRepository implements AttendanceSheetInterface
{

    public function __construct(AttendanceSheet $model)
    {
        parent::__construct($model);
    }


}
