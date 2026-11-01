<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseImportData extends Data
{
    /** @param  list<ExpenseImportRowInputData>  $rows */
    public function __construct(
        #[Required, Max(40)]
        public readonly string $account_iban,
        #[DataCollectionOf(ExpenseImportRowInputData::class)]
        public readonly array $rows,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:'.config()->integer('expenses.statement.max_rows')],
            'rows.*.reference' => ['distinct:strict'],
        ];
    }
}
