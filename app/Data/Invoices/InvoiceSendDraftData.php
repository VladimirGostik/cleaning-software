<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\InvoiceEmailSaveTargetEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceSendDraftData extends Data
{
    /**
     * @param  string[]  $email_options
     */
    public function __construct(
        public readonly ?string $recipient_email,
        public readonly array $email_options,
        public readonly ?InvoiceEmailSaveTargetEnum $save_target,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $attachment_name,
    ) {}
}
