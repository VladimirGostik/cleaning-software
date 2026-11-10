<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseDraftLineData extends Data
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $amount,
        public readonly ?string $payroll_month,
        public readonly ?string $tenant_membership_id = null,
        public readonly ?string $employee_label = null,
    ) {}
}
