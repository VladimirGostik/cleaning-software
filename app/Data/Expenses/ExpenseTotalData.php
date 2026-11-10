<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\CurrencyEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseTotalData extends Data
{
    public function __construct(
        public readonly CurrencyEnum $currency,
        public readonly string $amount,
        public readonly int $count,
    ) {}
}
