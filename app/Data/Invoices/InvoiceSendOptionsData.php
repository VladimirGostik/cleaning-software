<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\InvoiceEmailSaveTargetEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceSendOptionsData extends Data
{
    /** @param list<string> $email_options */
    public function __construct(
        public readonly array $email_options,
        public readonly ?InvoiceEmailSaveTargetEnum $save_target,
    ) {}
}
