<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GymWorkingDayTimeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'week_day' => $this->week_day,
            'day_start_time' => $this->day_start_time,
            'day_end_time' => $this->day_end_time,
            'break_start_time' => $this->break_start_time,
            'break_end_time' => $this->break_end_time,
        ];
    }
}
