<?php

declare(strict_types=1);

namespace App\Services\Expenses;

final readonly class CamtStatement
{
    /**
     * @param  list<StatementRow>  $debitRows
     * @param  list<StatementRow>  $creditRows
     */
    public function __construct(
        public string $iban,
        public array $debitRows,
        public int $skippedCreditRows,
        public int $skippedOtherRows,
        public array $creditRows = [],
        public ?string $periodFrom = null,
        public ?string $periodTo = null,
    ) {}
}
