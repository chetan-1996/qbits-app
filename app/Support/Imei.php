<?php

namespace App\Support;

class Imei
{
    /**
     * Normalize an IMEI read from Excel/CSV.
     * Numeric cells (Excel shows 8.65056E+14) are converted to a full
     * integer string from the stored value, never from displayed text.
     * Spaces and dashes are stripped.
     */
    public static function normalize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return sprintf('%.0f', $value);
        }

        $s = str_replace([' ', '-'], '', trim((string) $value));

        // Numeric string containing a decimal point or exponent notation
        if ($s !== '' && is_numeric($s) && !preg_match('/^\d+$/', $s)) {
            return sprintf('%.0f', (float) $s);
        }

        return $s;
    }

    /**
     * Exactly 15 digits and a valid Luhn check digit (the 15th digit).
     */
    public static function isValid(string $imei): bool
    {
        if (!preg_match('/^\d{15}$/', $imei)) {
            return false;
        }

        return self::luhnValid($imei);
    }

    public static function luhnValid(string $digits): bool
    {
        $sum = 0;
        $len = strlen($digits);

        for ($i = 0; $i < $len; $i++) {
            $d = (int) $digits[$len - 1 - $i];
            if ($i % 2 === 1) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }

        return $sum % 10 === 0;
    }
}
