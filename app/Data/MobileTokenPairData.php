<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MobileTokenPairData extends Data
{
    public function __construct(
        public readonly string $token,
        public readonly string $refresh_token,
    ) {}
}
