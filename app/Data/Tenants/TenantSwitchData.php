<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TenantSwitchData extends Data
{
    public function __construct(
        #[Nullable, StringType, Max(2000), Regex('/^\/(?![\/\\\\])/')]
        public readonly ?string $redirect_to = null,
    ) {}
}
