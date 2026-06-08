<?php

namespace App\DTO\Memberships;

class MembershipCategoryDTO
{
    public function __construct(
        public ?int $gym_id,
        public bool $active,
        public string $slug,
        public array $translations, // ['en' => ['name' => '', 'description' => ''], ...]
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
            gym_id: $data['gym_id'] ?? null,
            active: (bool) ($data['active'] ?? true),
            slug: $data['slug'] ?? '',
            translations: $translations,
        );
    }
}