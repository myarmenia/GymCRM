<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileMembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->membershipPlan;
        $category = $plan?->MembershipCategory;
        $trainerName = $this->trainer
            ? trim("{$this->trainer->name} {$this->trainer->surname}")
            : null;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'valid_at' => $this->valid_at?->toDateString(),
            'membership_plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name ?? $plan->translations->first()?->name,
                'type' => $category?->name ?? $category?->translations->first()?->name,
                'duration_type' => $plan->duration_type,
                'duration_value' => $plan->duration_value,
                'visits_allowed' => $plan->visits_limit,
                'guests_allowed' => $plan->guest_limit,
                'freezes_allowed' => $plan->freeze_limit,
            ] : null,
            'gym' => $this->gym ? ['id' => $this->gym->id, 'name' => $this->gym->name] : null,
            'trainer' => $this->trainer ? ['id' => $this->trainer->id, 'name' => $trainerName ?: null] : null,
            'prices' => $this->membershipSale ? [
                'original_price' => $this->membershipSale->total_price,
                'final_price' => $this->membershipSale->final_price,
            ] : null,
            'usage' => [
                'visits_used' => $this->visits_used,
                'visits_remaining' => $this->visits_left,
                'guests_used' => $this->guest_used,
                'guests_remaining' => $this->guest_left,
                'freezes_used' => $this->freeze_used,
                'freezes_remaining' => $this->freeze_left,
            ],
        ];
    }
}
