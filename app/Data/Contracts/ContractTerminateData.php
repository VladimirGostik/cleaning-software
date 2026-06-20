<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractTerminateData extends Data
{
    public function __construct(
        #[Required]
        public string $terminated_at,
        #[Nullable]
        public ?string $termination_reason = null,
    ) {}
}
