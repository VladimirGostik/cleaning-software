<?php

declare(strict_types=1);

namespace App\Services\PaymentImport;

use App\Data\Invoices\PaymentImportFileData;

final readonly class PaymentImportAnalysis
{
    /**
     * @param  list<PaymentImportFileData>  $files
     * @param  list<PaymentImportEntry>  $entries
     */
    public function __construct(
        public array $files,
        public array $entries,
        public bool $hasMatchedFile,
        public int $ignoredDebitRows,
        public int $unreadableCreditRows,
        public int $overlappingRows,
    ) {}
}
