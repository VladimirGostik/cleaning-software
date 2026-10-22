<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\BulkActionEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class BulkActionResultData extends Data
{
    /** @param list<BulkSkippedItemData> $skipped */
    public function __construct(
        public readonly BulkActionEnum $action,
        public readonly int $processed,
        public readonly array $skipped,
    ) {}
}
