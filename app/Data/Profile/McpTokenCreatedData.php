<?php

declare(strict_types=1);

namespace App\Data\Profile;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class McpTokenCreatedData extends Data
{
    public function __construct(
        public readonly McpTokenData $token,
        public readonly string $plain_text_token,
    ) {}
}
