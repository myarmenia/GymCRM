<?php

namespace App\DTO\PersonMemberships;

class PersonMembershipDTO
{
    public function __construct(
        public int $membership_sale_id,
        public int $user_id,
        public int $person_id,
        public int $gym_id,
        public int $membership_plan_id,
        public ?int $trainer_id,
        public string $status,
        public ?string $start_date,
        public ?string $end_date,
        public int $visits_used,
        public ?int $visits_left,
        public int $freeze_used,
        public int $guest_used,
        public ?int $next_membership_id,
        public ?string $activated_at,
        public ?string $expired_at,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            membership_sale_id: (int) $data['membership_sale_id'],
            user_id: (int) $data['user_id'],
            person_id: (int) $data['person_id'],
            gym_id: (int) $data['gym_id'],
            membership_plan_id: (int) $data['membership_plan_id'],
            trainer_id: isset($data['trainer_id']) ? (int) $data['trainer_id'] : null,
            status: $data['status'] ?? 'waiting',
            start_date: $data['start_date'] ?? null,
            end_date: $data['end_date'] ?? null,
            visits_used: (int) ($data['visits_used'] ?? 0),
            visits_left: isset($data['visits_left']) ? (int) $data['visits_left'] : null,
            freeze_used: (int) ($data['freeze_used'] ?? 0),
            guest_used: (int) ($data['guest_used'] ?? 0),
            next_membership_id: isset($data['next_membership_id']) ? (int) $data['next_membership_id'] : null,
            activated_at: $data['activated_at'] ?? null,
            expired_at: $data['expired_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'membership_sale_id' => $this->membership_sale_id,
            'user_id' => $this->user_id,
            'person_id' => $this->person_id,
            'gym_id' => $this->gym_id,
            'membership_plan_id' => $this->membership_plan_id,
            'trainer_id' => $this->trainer_id,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'visits_used' => $this->visits_used,
            'visits_left' => $this->visits_left,
            'freeze_used' => $this->freeze_used,
            'guest_used' => $this->guest_used,
            'next_membership_id' => $this->next_membership_id,
            'activated_at' => $this->activated_at,
            'expired_at' => $this->expired_at,
        ];
    }
}
