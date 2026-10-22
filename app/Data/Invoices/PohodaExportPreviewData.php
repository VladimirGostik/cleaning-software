<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PohodaExportPreviewData extends Data
{
    public function __construct(
        public readonly int $count,
        public readonly int $skipped_non_eur,
    ) {}
}
