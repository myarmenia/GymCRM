<?php

namespace App\Models;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Gym extends Model
{
    use HasUuidAndVersion, SoftDeletes;

    public const DEFAULT_ENTRY_CODE_TYPE = 'rfId';

    public const ENTRY_CODE_TYPES = [self::DEFAULT_ENTRY_CODE_TYPE, 'FaceId'];

    public const TRAINER_SALARY_MODE_PREPAID = 'prepaid';

    public const TRAINER_SALARY_MODE_POSTPAID = 'postpaid';

    protected $guarded = [];

    protected $hidden = [
        'version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'trainer_salary_mode' => 'string',
        ];
    }

    public static function resolveEntryCodeType(?string $type): string
    {
        return in_array($type, self::ENTRY_CODE_TYPES, true)
            ? $type
            : self::DEFAULT_ENTRY_CODE_TYPE;
    }

    public function resolvedEntryCodeType(): string
    {
        return self::resolveEntryCodeType($this->entry_code_type);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function partners()
    {
        return $this->hasMany(Partner::class);
    }

    public function client_working_day_times(): HasMany
    {
        return $this->hasMany(GymWorkingDayTime::class);
    }

    public function entryCodes()
    {
        return $this->hasMany(EntryCode::class, 'gym_id');
    }

    public function people()
    {
        return $this->belongsToMany(Person::class, 'gym_person');
    }

    public function membershipSales()
    {
        return $this->hasMany(MembershipSale::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function personMemberships()
    {
        return $this->hasMany(PersonMembership::class);
    }

    public function salaryPayouts()
    {
        return $this->hasMany(SalaryPayout::class);
    }

    public function languages()
    {
        return $this->belongsToMany(Lang::class, 'gym_languages')
            ->using(GymLanguage::class)
            ->withPivot(['active', 'uuid', 'version']);

    }

    protected static function booted(): void
    {
        static::deleting(function (Gym $gym) {

            if ($gym->logo && Storage::disk('public')->exists($gym->logo)) {
                Storage::disk('public')->delete($gym->logo);
            }
        });

        static::created(function (Gym $gym) {

            foreach (Lang::query()->get() as $lang) {
                $gym->languages()->attach($lang->id, [
                    'active' => true,
                ]);
            }
        });
    }
}
