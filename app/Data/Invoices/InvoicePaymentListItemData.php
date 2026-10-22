<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\PaymentSourceEnum;
use App\Enums\PaymentTypeEnum;
use App\Models\InvoicePayment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoicePaymentListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $paid_on,
        public readonly string $amount,
        public readonly PaymentTypeEnum $payment_type,
        public readonly PaymentSourceEnum $source,
        public readonly ?string $note,
        public readonly ?string $recorded_by_name,
        public readonly string $created_at,
    ) {}

    public static function fromModel(InvoicePayment $payment): self
    {
        return new self(
            id: $payment->id,
            paid_on: $payment->paid_on->toDateString(),
            amount: $payment->amount,
            payment_type: $payment->payment_type,
            source: $payment->source,
            note: $payment->note,
            recorded_by_name: $payment->recordedBy?->name,
            created_at: $payment->created_at->toIso8601String(),
        );
    }
}
