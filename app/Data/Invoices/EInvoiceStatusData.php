<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\EfaStatusEnum;
use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EInvoiceStatusData extends Data
{
    /** @param list<string> $missing_fields */
    public function __construct(
        public readonly EfaStatusEnum $status,
        public readonly ?string $efa_id,
        public readonly ?string $submitted_at,
        public readonly ?string $last_error,
        public readonly array $missing_fields,
        public readonly bool $xml_available,
        public readonly bool $can_submit,
    ) {}

    /** @param list<string> $missing */
    public static function fromInvoice(Invoice $invoice, array $missing, bool $driverConfigured = false): self
    {
        $draft = $invoice->status === InvoiceStatusEnum::Draft;

        return new self(
            status: $invoice->efa_status,
            efa_id: $invoice->efa_id,
            submitted_at: $invoice->efa_submitted_at?->toIso8601String(),
            last_error: $invoice->efa_last_error,
            missing_fields: $missing,
            xml_available: $invoice->status !== InvoiceStatusEnum::Draft && $invoice->number !== null && $missing === [],
            can_submit: $driverConfigured && ! $draft && $missing === [] && $invoice->efa_status->canSubmit(),
        );
    }
}
