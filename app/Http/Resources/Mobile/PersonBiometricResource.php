<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonBiometricResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'height' => $this->height,
            'weight' => $this->weight,
            'goal' => $this->goal,
            'activity_level' => $this->activity_level,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
