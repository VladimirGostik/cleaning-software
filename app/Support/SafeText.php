<?php

declare(strict_types=1);

namespace App\Support;

final class SafeText
{
    public static function clean(?string $value, int $max, bool $neutraliseFormulas = true): ?string
    {
        if ($value === null) {
            return null;
        }

        $collapsed = preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $value) ?? '';
        $safe = $neutraliseFormulas ? ltrim(trim($collapsed), '=+-@ ') : trim($collapsed);
        $limited = mb_substr($safe, 0, $max);

        return $limited === '' ? null : $limited;
    }
}
