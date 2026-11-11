<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TokenContractResultData extends Data
{
    public function __construct(
        public readonly string $contract_id,
        public readonly ?string $attachment_id,
        public readonly bool $created,
        public readonly string $url,
    ) {}
}
