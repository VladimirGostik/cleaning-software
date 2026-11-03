<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Str;

final class ActivityBatch
{
    private static ?string $uuid = null;

    public static function current(): ?string
    {
        return self::$uuid;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Closure $callback): mixed
    {
        if (self::$uuid !== null) {
            return $callback();
        }

        self::$uuid = (string) Str::uuid7();

        try {
            return $callback();
        } finally {
            self::$uuid = null;
        }
    }
}
