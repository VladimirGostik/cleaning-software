<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PlaceholderTokenData extends Data
{
    public function __construct(
        public readonly string $token,
        public readonly string $label,
    ) {}
}
