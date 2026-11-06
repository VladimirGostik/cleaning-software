<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceSendData extends Data
{
    public function __construct(
        #[Nullable, Email, Max(255)]
        public readonly ?string $recipient_email = null,
        public readonly bool $save_for_future = false,
        #[Nullable, Max(255), Regex('/^[^\r\n]*$/')]
        public readonly ?string $subject = null,
        #[Nullable, Max(5000)]
        public readonly ?string $body = null,
    ) {}
}
