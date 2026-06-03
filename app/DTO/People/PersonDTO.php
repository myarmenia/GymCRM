<?php

namespace App\DTO\People;

class PersonDTO
{
    public function __construct(
        public ?int $gym_id,
        public ?string $name,
        public ?string $surname,
        public ?string $image,
        public ?string $email,
        public ?string $phone,
        public string $type,
        public ?int $entry_code_id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gym_id: $data['gym_id'] ?? null,
            name: $data['name'] ?? null,
            surname: $data['surname'] ?? null,
            image: $data['image'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            type: $data['type'] ?? 'visitor',
            entry_code_id: $data['entry_code_id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'gym_id' => $this->gym_id,
            'name' => $this->name,
            'surname' => $this->surname,
            'image' => $this->image,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
        ];
    }
}