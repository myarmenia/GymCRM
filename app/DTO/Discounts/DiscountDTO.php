<?php

namespace App\DTO\Discounts;

class DiscountDTO
{
    public function __construct(
        public string $type,
        public float $value,
        public ?string $start_date,
        public ?string $end_date,
        public bool $status,
        public array $translations,
        public array $membership_plan_ids,
    ) {}

    public static function fromArray(array $data): self
    {
        $translations = [];
        foreach ($data['translations'] ?? [] as $locale => $trans) {
            $translations[$locale] = [
                'name' => $trans['name'] ?? '',
                'description' => $trans['description'] ?? null,
            ];
        }

        return new self(
            type: $data['type'] ?? 'percent',
            value: (float) ($data['value'] ?? 0),
            start_date: $data['start_date'] ?? null,
            end_date: $data['end_date'] ?? null,
            status: (bool) ($data['status'] ?? true),
            translations: $translations,
            membership_plan_ids: $data['membership_plan_ids'] ?? [],
        );
    }
}
