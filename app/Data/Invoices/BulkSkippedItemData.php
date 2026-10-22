<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\BulkSkipReasonEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class BulkSkippedItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $number,
        public readonly BulkSkipReasonEnum $reason,
    ) {}
}
