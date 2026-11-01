<?php

declare(strict_types=1);

namespace App\Data\DocumentTemplates;

use App\Enums\DocumentTokenGroupEnum;
use App\Enums\DocumentTokenKindEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DocumentTokenData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly DocumentTokenKindEnum $kind,
        public readonly DocumentTokenGroupEnum $group,
    ) {}
}
