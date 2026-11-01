<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class BankCsvImportData extends Data
{
    /** @param  list<ExpenseImportRowData>  $rows */
    public function __construct(
        public readonly BankCsvMappingData $mapping,
        #[DataCollectionOf(ExpenseImportRowData::class), Min(1), Max(100)]
        public readonly array $rows,
    ) {}
}
