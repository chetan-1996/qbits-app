<?php

namespace App\Support;

class Fwver
{
    /**
     * Extract the running firmware version from a telemetry/heartbeat
     * payload: the value of the first key ending in "---FWVER".
     */
    public static function extract(array $payload): ?string
    {
        foreach ($payload as $key => $value) {
            if (is_string($key) && str_ends_with($key, '---FWVER') && $value !== null && $value !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
