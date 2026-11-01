<?php

declare(strict_types=1);

namespace App\Services\Expenses;

final readonly class StatementExtraction
{
    /** @param  list<StatementRow>  $debitRows */
    public function __construct(
        public array $debitRows,
        public int $skippedCreditRows,
    ) {}
}
