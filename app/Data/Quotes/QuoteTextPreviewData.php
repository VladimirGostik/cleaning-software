<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteTextPreviewData extends Data
{
    public function __construct(
        /** @var array<string, string|null> */
        public readonly array $values,
        /** @var array<string, string> */
        public readonly array $blocks,
        /** @var string[] */
        public readonly array $missing_tokens,
    ) {}
}
