<?php

declare(strict_types=1);

namespace App\Actions;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class GenerateUuid
{
    public function handle(): UuidInterface
    {
        return Uuid::uuid7();
    }
}
