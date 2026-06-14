<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TabCountsData extends Data
{
    public function __construct(
        public readonly ?int $all,
        public readonly int $all_issued,
        public readonly int $recurring,
        public readonly int $drafts,
        public readonly int $overdue,
    ) {}
}
