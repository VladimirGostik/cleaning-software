<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TokenExpenseReceiptResultData extends Data
{
    /** @param  list<string>  $expense_ids */
    public function __construct(
        public readonly array $expense_ids,
        public readonly string $file_name,
    ) {}
}
