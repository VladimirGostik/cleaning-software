<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseStatementRowsData extends Data
{
    /**
     * @param  list<ExpenseStatementRowData>  $rows
     * @param  list<string>  $include_references
     * @param  list<string>  $exclude_references
     */
    public function __construct(
        #[Required, Max(40)]
        public readonly string $account_iban,
        #[DataCollectionOf(ExpenseStatementRowData::class)]
        public readonly array $rows,
        public readonly array $include_references = [],
        public readonly array $exclude_references = [],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'list', 'min:1', 'max:'.config()->integer('expenses.statement.max_new_rows')],
            'rows.*.reference' => ['distinct:strict'],
            'include_references' => ['array', 'list', 'max:'.config()->integer('expenses.statement.max_new_rows')],
            'include_references.*' => ['string', 'max:100'],
            'exclude_references' => ['array', 'list', 'max:'.config()->integer('expenses.statement.max_new_rows')],
            'exclude_references.*' => ['string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'rows.*.direction.in' => __('app.expense_bank_rows_debit_only'),
            'rows.*.currency.in' => __('app.expense_bank_rows_debit_only'),
            'rows.*.reference.regex' => __('app.expense_bank_rows_reference_format'),
        ];
    }
}
