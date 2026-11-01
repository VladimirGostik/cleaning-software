<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Data;

final class ExpenseCompanyContextData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $ico,
        public readonly ?string $iban,
    ) {}
}
