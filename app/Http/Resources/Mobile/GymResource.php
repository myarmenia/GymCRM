<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class GymResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membership = $this->whenLoaded('personMemberships')->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'logo_url' => $this->logo ? url(Storage::disk('public')->url($this->logo)) : null,
            'working_days' => GymWorkingDayTimeResource::collection($this->whenLoaded('client_working_day_times')),
            'is_linked' => (bool) $this->is_linked,
            'has_eligible_membership' => $membership !== null,
            'membership' => $membership ? [
                'status' => $membership->status,
                'start_date' => $membership->start_date?->toDateString(),
                'end_date' => $membership->end_date?->toDateString(),
                'visits_left' => $membership->visits_left,
            ] : null,
        ];
    }
}
