<?php

namespace App\DTO\People;

use Illuminate\Http\UploadedFile;

class PersonDTO
{
    public function __construct(
        public string $name,
        public ?string $surname,
        public UploadedFile|string|null $image,
        public string $email,
        public string $password,
        public string $phone,
        public string $type,
        public string $birth_date,
        public ?int $entry_code_id = null,
        public ?string $gender = null,
        public bool $mobile_deleted = false,
        public ?string $fcm_token = null,
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
            birth_date: $data['birth_date'] ?? '',
            entry_code_id: $data['entry_code_id'] ?? null,
            gender: $data['gender'] ?? null,
            mobile_deleted: (bool) ($data['mobile_deleted'] ?? false),
            fcm_token: $data['fcm_token'] ?? null,
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
            'birth_date' => $this->birth_date,
            'gender' => $this->gender,
            'mobile_deleted' => $this->mobile_deleted,
            'fcm_token' => $this->fcm_token,
        ];
    }
}
