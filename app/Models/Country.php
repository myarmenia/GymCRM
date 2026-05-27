<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $guarded = [];
    protected $appends = ['name'];

    public function translations()
    {
        return $this->hasMany(CountryTranslation::class);
    }

    public function translation()
    {
        return $this->hasOne(CountryTranslation::class)
            ->where('locale', app()->getLocale());
    }

    public function getNameAttribute()
    {
        return $this->translation?->name ?? $this->slug;
    }
}
