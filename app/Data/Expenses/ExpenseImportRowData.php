<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\ExpenseCategoryEnum;
use App\Enums\ExpenseImportRowStateEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseImportRowData extends Data
{
    public function __construct(
        public readonly string $reference,
        public readonly string $date,
        public readonly string $amount,
        public readonly ?string $counterparty,
        public readonly ?string $note,
        public readonly ?ExpenseCategoryEnum $proposed_category,
        public readonly float $confidence,
        public readonly ExpenseImportRowStateEnum $state,
        public readonly ?string $existing_expense_id,
        public readonly bool $include,
    ) {}
}
