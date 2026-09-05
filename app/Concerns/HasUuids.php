<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Actions\GenerateUuid;

trait HasUuids
{
    use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected static ?string $forcedUuid = null;

    public static function forceUuid(?string $uuid): void
    {
        static::$forcedUuid = $uuid;
    }

    public function newUniqueId(): string
    {
        if (static::$forcedUuid !== null) {
            $uuid = static::$forcedUuid;
            static::$forcedUuid = null;

            return $uuid;
        }

        return new GenerateUuid()->handle()->toString();
    }
}
