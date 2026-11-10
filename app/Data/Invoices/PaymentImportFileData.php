<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Data\Expenses\ExpenseImportAccountCheckData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportFileData extends Data
{
    public function __construct(
        public readonly int $index,
        public readonly string $name,
        public readonly string $iban,
        public readonly ?string $period_from,
        public readonly ?string $period_to,
        public readonly int $credit_rows,
        public readonly ExpenseImportAccountCheckData $account,
    ) {}
}
