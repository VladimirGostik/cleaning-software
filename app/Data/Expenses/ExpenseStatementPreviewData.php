<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseStatementPreviewData extends Data
{
    /**
     * @param  array<string, int>  $counts
     * @param  list<ExpenseStatementPreviewRowData>  $rows
     */
    public function __construct(
        public readonly ExpenseImportAccountCheckData $account_check,
        public readonly array $counts,
        public readonly string $amount_total,
        public readonly array $rows,
        public readonly ?string $security_notice,
    ) {}
}
