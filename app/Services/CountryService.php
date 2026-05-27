<?php

namespace App\Services;

use App\Models\Country;
use Illuminate\Support\Facades\Cache;

class CountryService
{
    public static function all()
    {
        return Cache::rememberForever('countries', function () {
            return Country::with('translations')->orderBy('id')->get();
        });
    }
}
