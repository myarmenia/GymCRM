<?php

namespace App\Helpers;

class TimeFormatHelper
{
    public static function formatSeconds($seconds, $type = 'default')
    {
        $seconds = (int) $seconds;

        if ($seconds < 60) {
            return ''; // ВАЖНО: убрали 0 и 11 секунд
        }

        $minutes = floor($seconds / 60);

        if ($type === 'minutes') {
            return $minutes . ' ր';
        }

        if ($type === 'hours') {
            $hours = $minutes / 60;

            if ($hours < 0.01) {
                return ''; // тоже скрываем мусор
            }

            return number_format($hours, 2) . ' ժ';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0 && $remainingMinutes === 0) {
            return '';
        }

        return "{$hours} ժ {$remainingMinutes} ր";
    }
}
