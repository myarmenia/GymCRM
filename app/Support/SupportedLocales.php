<?php

namespace App\Support;

use Illuminate\Http\Request;

final class SupportedLocales
{
    public const CODES = ['hy', 'en', 'ru'];

    public const DEFAULT = 'hy';

    public static function resolve(Request $request): string
    {
        foreach ([$request->route('locale'), $request->session()->get('locale'), config('app.locale')] as $locale) {
            if (is_string($locale) && in_array($locale, self::CODES, true)) {
                return $locale;
            }
        }

        return self::DEFAULT;
    }
}
