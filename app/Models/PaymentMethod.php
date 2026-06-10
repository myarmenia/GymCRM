<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $guarded = [];
    protected $appends = ['name'];


    public function translations()
    {
        return $this->hasMany(PaymentMethodTranslation::class);
    }

    public function translation($locale = null)
    {
        $locale = $locale ?? app()->getLocale();

        return $this->translations->where('locale', $locale)->first();
    }

    public function cardTypes()
    {
        return $this->belongsToMany(CardType::class);
    }

    public function membershipPlanPayments()
    {
        return $this->hasMany(MembershipPlanPayment::class);
    }

    public function getNameAttribute()
    {
        return $this->translations
            ->where('locale', app()->getLocale())
            ->first()?->name
            ?? $this->translations->first()?->name;
    }
}
