<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseImportResultData extends Data
{
    public function __construct(
        public readonly int $created,
        public readonly int $skipped_duplicates,
    ) {}
}
