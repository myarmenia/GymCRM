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

    protected $guarded = [];

    protected $hidden = [
        'version',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
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
            ->withPivot('active');

    }

    protected static function booted(): void
    {
        static::deleting(function (Gym $gym) {

            if ($gym->logo && Storage::disk('public')->exists($gym->logo)) {
                Storage::disk('public')->delete($gym->logo);
            }
        });

        static::created(function (Gym $gym) {

            $hyLang = Lang::where('code', 'hy')->first();

            if ($hyLang) {
                $gym->languages()->attach($hyLang->id, [
                    'active' => true,
                ]);
            }
        });
    }
}
