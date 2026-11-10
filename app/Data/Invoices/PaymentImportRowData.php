<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\PaymentImportRowStateEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportRowData extends Data
{
    public function __construct(
        public readonly string $reference,
        public readonly int $file_index,
        public readonly string $date,
        public readonly string $amount,
        public readonly ?string $payer_name,
        public readonly ?string $payer_iban,
        public readonly ?string $variable_symbol,
        public readonly ?string $note,
        public readonly PaymentImportRowStateEnum $state,
        public readonly ?PaymentImportInvoiceData $invoice,
        public readonly ?string $balance_before,
        public readonly ?string $amount_to_record,
        public readonly ?string $excess,
        public readonly ?int $instalment_position,
        public readonly ?int $instalment_count,
        public readonly ?string $existing_payment_id,
        public readonly bool $include,
    ) {}

    public function withoutFreeText(): self
    {
        return new self(
            $this->reference,
            $this->file_index,
            $this->date,
            $this->amount,
            null,
            $this->payer_iban,
            $this->variable_symbol,
            null,
            $this->state,
            $this->invoice,
            $this->balance_before,
            $this->amount_to_record,
            $this->excess,
            $this->instalment_position,
            $this->instalment_count,
            $this->existing_payment_id,
            $this->include,
        );
    }
}
