<?php

namespace App\DTO\User;

use App\DTO\DocumentDTO;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;


class UserDTO
{
    public function __construct(
        public ?string $name,
        public ?string $surname,
        public ?string $phone,
        public ?string $email,
        public ?array $roles,

        public ?string $passport_number,
        public ?Carbon $passport_expire_at,
        public ?Carbon $birth_date,
        public ?bool $active,
        public ?string $password,

        public ?int $gym_id,
        public ?int $entry_code_id
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'] ?? null,
            $data['surname'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['roles'] ?? null,

            $data['passport_number'] ?? null,
            isset($data['passport_expire_at']) ? Carbon::parse($data['passport_expire_at']) : null,
            isset($data['birth_date']) ? Carbon::parse($data['birth_date']) : null,

            $data['active'] ?? null,
            $data['password'] ?? null,
            $data['gym_id'] ?? null,
            $data['entry_code_id'] ?? null

        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'surname' => $this->surname,
            'phone' => $this->phone,
            'email' => $this->email,
            'passport_number' => $this->passport_number,
            'passport_expire_at' => $this->passport_expire_at,
            'birth_date' => $this->birth_date,
            'active' => $this->active,
            'password' => $this->password ? bcrypt($this->password) : null,
            'gym_id' => $this->gym_id

        ];
    }
}
