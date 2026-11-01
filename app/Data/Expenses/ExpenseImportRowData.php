<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\ExpenseCategoryEnum;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseImportRowData extends Data
{
    public function __construct(
        #[Min(1)]
        public readonly int $row,
        #[Required, DateFormat('Y-m-d')]
        public readonly string $date,
        #[Required, Rule(['numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'])]
        public readonly string $amount,
        #[Nullable, Max(200)]
        public readonly ?string $counterparty,
        #[Nullable, Max(500)]
        public readonly ?string $note,
        #[Nullable, Max(100)]
        public readonly ?string $reference,
        public readonly ?ExpenseCategoryEnum $proposed_category,
        #[Between(0, 1)]
        public readonly float $confidence,
        public readonly bool $duplicate,
        public readonly bool $include,
    ) {}
}
