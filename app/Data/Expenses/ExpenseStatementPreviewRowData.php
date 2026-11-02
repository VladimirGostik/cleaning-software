<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\ExpenseImportRowStateEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseStatementPreviewRowData extends Data
{
    public function __construct(
        public readonly string $reference,
        public readonly string $date,
        public readonly string $amount,
        public readonly ExpenseImportRowStateEnum $state,
        public readonly ?string $existing_expense_id,
        public readonly bool $include,
        public readonly bool $text_withheld,
        public readonly bool $existing_differs,
    ) {}
}
