<?php

declare(strict_types=1);

namespace App\Services\PaymentImport;

use App\Services\Expenses\StatementRow;

final readonly class PaymentImportEntry
{
    public function __construct(
        public StatementRow $row,
        public int $fileIndex,
        public string $key,
    ) {}

    public function cents(): int
    {
        return (int) round((float) $this->row->amount * 100);
    }
}
