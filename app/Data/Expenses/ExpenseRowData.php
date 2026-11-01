<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Spatie\LaravelData\Data;

final class ExpenseRowData extends Data
{
    public function __construct(
        public readonly int $row,
        public readonly string $amount,
        public readonly ?string $counterparty,
        public readonly ?string $note,
    ) {}
}
