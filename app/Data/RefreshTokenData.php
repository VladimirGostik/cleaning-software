<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RefreshTokenData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $refresh_token,
    ) {}
}
