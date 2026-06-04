<?php

namespace App\Traits;


trait ModelTranslationTrait
{

    
    public function translation($locale = null)
    {
        $locale = $locale ?? app()->getLocale();

        return $this->translations->where('locale', $locale)->first();
    }


    public function getNameAttribute()
    {
        return $this->translations
            ->where('locale', app()->getLocale())
            ->first()?->name
            ?? $this->translations->first()?->name;
    }
}
