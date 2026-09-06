<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractTerminateData extends Data
{
    public function __construct(
        #[Required, Date]
        public readonly string $terminated_at,
        #[Nullable, Max(1000)]
        public readonly ?string $termination_reason = null,
    ) {}
}
