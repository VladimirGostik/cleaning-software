<?php

declare(strict_types=1);

namespace App\Data\Documents;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DocumentRenderData extends Data
{
    public function __construct(
        public readonly string $html,
        /** @var string[] */
        public readonly array $missing_tokens,
        /** @var string[] */
        public readonly array $unknown_tokens,
        public readonly ?DocumentLetterheadData $letterhead,
        /** @var string[] */
        public readonly array $footer,
    ) {}
}
