<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CreateRoleData extends Data
{
    /** @param array<int, string> $permissions */
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        public readonly array $permissions = [],
    ) {}
}
