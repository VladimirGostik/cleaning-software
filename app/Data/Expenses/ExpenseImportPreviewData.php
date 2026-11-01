<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseImportPreviewData extends Data
{
    /** @param  list<ExpenseImportRowData>  $rows */
    public function __construct(
        public readonly ExpenseImportAccountCheckData $account_check,
        public readonly array $rows,
        public readonly int $skipped_credit_rows,
        public readonly int $skipped_other_rows,
    ) {}
}
