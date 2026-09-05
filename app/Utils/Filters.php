<?php

declare(strict_types=1);

namespace App\Utils;

final class Filters
{
    public static function escapeLikeValue(string $value): string
    {
        return str_replace(
            ['\\', '_', '%'],
            ['\\\\', '\\_', '\\%'],
            $value,
        );
    }
}
