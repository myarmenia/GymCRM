<?php

namespace App\Repositories\EntryReports;

use App\Interfaces\EntryReports\EntryReportInterface;
use App\Models\EntryReport;
use App\Repositories\BaseRepository;

class EntryReportRepository extends BaseRepository implements EntryReportInterface
{
    public function __construct(EntryReport $model)
    {
        parent::__construct($model);
    }
}
