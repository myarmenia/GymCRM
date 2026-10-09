<?php

namespace App\Services\MembershipSales;

class ContactNotePhone
{
    /** @return list<string> */
    public static function variants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return [];
        }

        $phones = [$digits];
        if (strlen($digits) === 11 && str_starts_with($digits, '374')) {
            $phones[] = '0'.substr($digits, 3);
            $phones[] = substr($digits, 3);
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '0')) {
            $phones[] = '374'.substr($digits, 1);
            $phones[] = substr($digits, 1);
        } elseif (strlen($digits) === 8) {
            $phones[] = '0'.$digits;
            $phones[] = '374'.$digits;
        }

        return array_values(array_unique($phones));
    }

    public static function normalizedSql(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '+', ''), '-', ''), '(', ''), ')', ''), '.', '')";
    }
}
