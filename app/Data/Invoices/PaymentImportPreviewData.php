<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportPreviewData extends Data
{
    /**
     * @param  list<PaymentImportFileData>  $files
     * @param  list<PaymentImportRowData>  $rows
     */
    public function __construct(
        public readonly array $files,
        public readonly array $rows,
        public readonly int $ignored_debit_rows,
        public readonly int $unreadable_credit_rows,
        public readonly int $overlapping_rows,
    ) {}

    public function withoutFreeText(): self
    {
        return new self(
            $this->files,
            array_map(static fn (PaymentImportRowData $row): PaymentImportRowData => $row->withoutFreeText(), $this->rows),
            $this->ignored_debit_rows,
            $this->unreadable_credit_rows,
            $this->overlapping_rows,
        );
    }
}
