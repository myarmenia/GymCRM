<?php

namespace App\Support;

use Ramsey\Uuid\Uuid;

final class StableUuid
{
    public static function from(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'https://gym-crm.local/'.$key)->toString();
    }

    public static function seedIdentity(string $resource, string|int $key): array
    {
        return [
            'uuid' => self::from("seed:{$resource}:{$key}"),
            'version' => 1,
        ];
    }
}
