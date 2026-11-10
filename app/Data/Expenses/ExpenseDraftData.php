<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Enums\CurrencyEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseDraftData extends Data
{
    /** @param  list<ExpenseDraftLineData>  $lines */
    public function __construct(
        public readonly ?string $date,
        public readonly ?string $amount,
        public readonly CurrencyEnum $currency,
        public readonly ?string $vat_amount,
        public readonly ?string $supplier_name,
        public readonly ?string $description,
        public readonly ExpenseProposalData $proposal,
        public readonly ?string $payroll_month = null,
        public readonly array $lines = [],
    ) {}
}
