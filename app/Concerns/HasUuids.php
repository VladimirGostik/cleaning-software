<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids as EloquentHasUuids;
use Illuminate\Support\Str;

trait HasUuids
{
    use EloquentHasUuids;

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [$this->getKeyName()];
    }
}
