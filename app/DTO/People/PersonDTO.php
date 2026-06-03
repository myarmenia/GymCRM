<?php

namespace App\DTO\People;

class PersonDTO
{
    public function __construct(
        public string $name,            // required
        public ?string $surname,       // nullable
        public ?string $image,         // nullable
        public string $email,          // required
        public string $password,       // required
        public string $phone,          // required
        public string $type,           // required (default 'visitor')
        public ?int $entry_code_id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            surname: $data['surname'] ?? null,
            image: $data['image'] ?? null,
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
            phone: $data['phone'] ?? '',
            type: $data['type'] ?? 'visitor',
            entry_code_id: $data['entry_code_id'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'surname' => $this->surname,
            'image' => $this->image,
            'email' => $this->email,
            'password' => $this->password,
            'phone' => $this->phone,
            'type' => $this->type,
        ];
    }
}