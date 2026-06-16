<?php

namespace App\DTO\Memberships;

use Carbon\Carbon;

class MembershipPlanDTO
{
    public function __construct(
        public int $membership_category_id,
        public float $price,

        public string $duration_type,
        public ?int $duration_value,

        public ?int $visits_limit,

        public ?Carbon $start_date,
        public ?Carbon $end_date,

        public int $guest_limit,
        public int $freeze_limit,

        public bool $active,

        public array $translations,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            membership_category_id: $data['membership_category_id'],

            price: (float) $data['price'],

            duration_type: $data['duration_type'],

            duration_value: $data['duration_value'] ?? null,

            visits_limit: $data['visits_limit'] ?? null,

            start_date: isset($data['start_date'])
            ? Carbon::parse($data['start_date'])
            : null,

            end_date: isset($data['end_date'])
            ? Carbon::parse($data['end_date'])
            : null,

            guest_limit: $data['guest_limit'] ?? 0,

            freeze_limit: $data['freeze_limit'] ?? 0,

            active: (bool) ($data['active'] ?? true),

            translations: $data['translations'] ?? [],
        );
    }

    /**
     * Business logic preparation
     */
    public function prepare(): self
    {
        switch ($this->duration_type) {

            case 'day':
                $this->visits_limit = $this->duration_value;
                break;

            case 'month':
            case 'year':
            case 'period':
                $this->visits_limit = null;
                break;

            case 'visit':
                // visits_limit оставляем как пришёл с формы
                break;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'membership_category_id' => $this->membership_category_id,
            'price' => $this->price,
            'duration_type' => $this->duration_type,
            'duration_value' => $this->duration_value,
            'visits_limit' => $this->visits_limit,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'guest_limit' => $this->guest_limit,
            'freeze_limit' => $this->freeze_limit,
            'active' => $this->active,
        ];
    }
}
