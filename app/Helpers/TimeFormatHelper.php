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
            return $minutes . __('backend_messages.m');
        }

        if ($type === 'hours') {
            $hours = $minutes / 60;

            if ($hours < 0.01) {
                return ''; // тоже скрываем мусор
            }

            return number_format($hours, 2) . __('backend_messages.h');
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours === 0 && $remainingMinutes === 0) {
            return '';
        }

        return __('backend_messages.hours_h_minutes_m', [
            'hours' => $hours,
            'minutes' => $remainingMinutes,
        ]);
    }
}
