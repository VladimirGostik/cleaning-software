<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceBuyerPreviewResultData extends Data
{
    /** @param list<string> $email_options */
    public function __construct(
        public readonly InvoiceBuyerData $buyer,
        public readonly array $email_options,
    ) {}
}
