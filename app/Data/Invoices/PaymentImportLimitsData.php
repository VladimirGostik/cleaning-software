<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportLimitsData extends Data
{
    public function __construct(
        public readonly int $max_files,
        public readonly int $max_file_kb,
        public readonly int $max_rows,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config()->integer('invoicing.payment_import.max_files'),
            config()->integer('invoicing.payment_import.max_file_kb'),
            config()->integer('invoicing.payment_import.max_rows'),
        );
    }
}
