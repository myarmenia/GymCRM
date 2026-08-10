<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileScheduleEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
