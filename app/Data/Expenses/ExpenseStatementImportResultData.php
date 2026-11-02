<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseStatementImportResultData extends Data
{
    /** @param  list<string>  $text_withheld */
    public function __construct(
        public readonly int $created,
        public readonly int $already_imported,
        public readonly int $excluded,
        public readonly array $text_withheld,
        public readonly ?string $security_notice,
    ) {}
}
