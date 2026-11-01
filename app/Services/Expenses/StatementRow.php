<?php

declare(strict_types=1);

namespace App\Services\Expenses;

final readonly class StatementRow
{
    public function __construct(
        public int $row,
        public string $date,
        public string $amount,
        public ?string $counterparty,
        public ?string $note,
        public string $reference,
    ) {}
}
