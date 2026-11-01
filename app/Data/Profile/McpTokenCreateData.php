<?php

declare(strict_types=1);

namespace App\Data\Profile;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class McpTokenCreateData extends Data
{
    public function __construct(
        #[Required, Max(60)]
        public readonly string $name,
    ) {}
}
