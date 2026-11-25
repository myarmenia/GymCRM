<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class GymPerson extends Pivot
{
    use HasUuidAndVersion, SoftDeletes;

    protected $table = 'gym_person';

    protected $guarded = [];

    public $incrementing = true;

    protected function casts(): array
    {
        return ['is_blocked' => 'boolean', 'blocked_at' => 'datetime', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            $person = Person::on($link->getConnectionName())->withTrashed()->findOrFail($link->person_id);
            $gym = Gym::on($link->getConnectionName())->withTrashed()->findOrFail($link->gym_id);
            $link->uuid = self::identity($person->uuid, $gym->uuid);
            $link->type ??= $person->getRawOriginal('type') ?: 'visitor';
            $link->is_blocked ??= false;
        });
    }

    public static function identity(string $personUuid, string $gymUuid): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'gym-person:'.$personUuid.':'.$gymUuid)->toString();
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function gym()
    {
        return $this->belongsTo(Gym::class);
    }

    public function blockedBy()
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }
}
