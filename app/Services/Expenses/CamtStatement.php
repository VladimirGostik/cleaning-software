<?php

declare(strict_types=1);

namespace App\Services\Expenses;

final readonly class CamtStatement
{
    /** @param  list<StatementRow>  $debitRows */
    public function __construct(
        public string $iban,
        public array $debitRows,
        public int $skippedCreditRows,
        public int $skippedOtherRows,
    ) {}
}
