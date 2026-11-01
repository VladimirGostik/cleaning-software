<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\RequiredWithout;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class BankCsvMappingData extends Data
{
    public function __construct(
        #[Required, In([',', ';', "\t", '|'])]
        public readonly string $delimiter,
        #[Required, Max(100)]
        public readonly string $date_column,
        #[Required, Max(20), Regex('/^[dmyYjnHis.\-\/ :]+$/')]
        public readonly string $date_format,
        #[Nullable, Max(100), RequiredWithout('debit_column')]
        public readonly ?string $amount_column,
        #[Nullable, Max(100)]
        public readonly ?string $counterparty_column,
        #[Nullable, Max(100)]
        public readonly ?string $note_column,
        #[Nullable, Max(100)]
        public readonly ?string $reference_column,
        #[Required, In([',', '.']), LiteralTypeScriptType("',' | '.'")]
        public readonly string $decimal_separator,
        #[Nullable, Max(100)]
        public readonly ?string $debit_column = null,
        #[Nullable, Max(100)]
        public readonly ?string $credit_column = null,
    ) {}
}
