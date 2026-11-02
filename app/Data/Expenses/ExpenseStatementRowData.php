<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\DateFormat;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseStatementRowData extends Data
{
    public function __construct(
        #[Required, Max(100), Regex('/^[A-Za-z0-9._:\/+\-]+$/D')]
        public readonly string $reference,
        #[Required, DateFormat('Y-m-d'), BeforeOrEqual('tomorrow')]
        public readonly string $date,
        #[Required, Rule(['string', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'])]
        public readonly string $amount,
        #[Required, In('DBIT')]
        public readonly string $direction,
        #[Required, In('EUR')]
        public readonly string $currency,
        #[Nullable, Max(200)]
        public readonly ?string $counterparty = null,
        #[Nullable, Max(500)]
        public readonly ?string $note = null,
    ) {}
}
