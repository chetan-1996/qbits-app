<?php

namespace App\Support;

class SemVer
{
    /**
     * Compare two x.y.z versions. Returns -1, 0 or 1.
     * Missing or non-numeric parts are treated as 0.
     */
    public static function compare(?string $a, ?string $b): int
    {
        $pa = array_map('intval', explode('.', (string) $a));
        $pb = array_map('intval', explode('.', (string) $b));

        for ($i = 0; $i < 3; $i++) {
            $diff = ($pa[$i] ?? 0) <=> ($pb[$i] ?? 0);
            if ($diff !== 0) {
                return $diff;
            }
        }

        return 0;
    }

    public static function isValid(?string $version): bool
    {
        return is_string($version) && preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;
    }
}
