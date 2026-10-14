<?php

namespace App\DTO\People;

use Illuminate\Http\UploadedFile;

class PersonDTO
{
    public function __construct(
        public string $name,
        public ?string $surname,
        public UploadedFile|string|null $image,
        public ?string $email,
        public ?string $password,
        public ?string $phone,
        public string $type,
        public string $birth_date,
        public ?int $entry_code_id = null,
        public string $entry_code_mode = 'existing',
        public ?string $entry_code_token = null,
        public ?string $gender = null,
        public bool $mobile_deleted = false,
        public ?string $fcm_token = null,
        public ?string $passport_series = null,
        public ?string $passport_issued_by = null,
        public ?string $passport_issued_at = null,
        public ?string $address = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            surname: $data['surname'] ?? null,
            image: $data['image'] ?? null,
            email: $data['email'] ?? null,
            password: $data['password'] ?? null,
            phone: $data['phone'] ?? null,
            type: $data['type'] ?? 'visitor',
            birth_date: $data['birth_date'] ?? '',
            entry_code_id: $data['entry_code_id'] ?? null,
            entry_code_mode: $data['entry_code_mode'] ?? 'existing',
            entry_code_token: $data['entry_code_token'] ?? null,
            gender: $data['gender'] ?? null,
            mobile_deleted: (bool) ($data['mobile_deleted'] ?? false),
            fcm_token: $data['fcm_token'] ?? null,
            passport_series: $data['passport_series'] ?? null,
            passport_issued_by: $data['passport_issued_by'] ?? null,
            passport_issued_at: $data['passport_issued_at'] ?? null,
            address: $data['address'] ?? null,
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
            'passport_series' => $this->passport_series,
            'passport_issued_by' => $this->passport_issued_by,
            'passport_issued_at' => $this->passport_issued_at,
            'address' => $this->address,
        ];
    }
}
