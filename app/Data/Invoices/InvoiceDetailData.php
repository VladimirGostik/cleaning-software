<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceDetailData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $client_id,
        public readonly ?string $client_name,
        public readonly ?string $cleaning_object_id,
        public readonly ?string $credited_invoice_id,
        public readonly ?string $credit_note_id,
        public readonly ?string $recurring_invoice_id,
        public readonly InvoiceTypeEnum $type,
        public readonly InvoiceStatusEnum $status,
        public readonly InvoiceTemplateEnum $template,
        public readonly ?string $number,
        public readonly ?string $variable_symbol,
        public readonly ?string $period_from,
        public readonly ?string $period_to,
        public readonly string $issue_date,
        public readonly string $delivery_date,
        public readonly string $due_date,
        public readonly ?string $issued_at,
        public readonly ?string $sent_at,
        public readonly ?string $paid_at,
        public readonly ?string $cancelled_at,
        public readonly bool $is_vat_payer,
        public readonly ?string $vat_rate,
        public readonly string $subtotal,
        public readonly string $vat_amount,
        public readonly string $total,
        public readonly string $deposit,
        public readonly string $balance_due,
        public readonly string $rounding_amount,
        public readonly PaymentTypeEnum $payment_type,
        public readonly CurrencyEnum $currency,
        public readonly RoundingModeEnum $rounding_mode,
        public readonly ?string $constant_symbol,
        public readonly ?string $specific_symbol,
        public readonly ?string $header_text,
        public readonly ?string $footer_text,
        public readonly string $customer_name,
        public readonly ?string $customer_representative,
        public readonly ?string $customer_ico,
        public readonly ?string $customer_dic,
        public readonly ?string $customer_vat_number,
        public readonly ?string $customer_street,
        public readonly ?string $customer_city,
        public readonly ?string $customer_postal_code,
        public readonly ?string $customer_country,
        public readonly ?string $customer_email,
        public readonly ?string $object_name,
        public readonly ?string $object_street,
        public readonly ?string $object_city,
        public readonly ?string $object_postal_code,
        public readonly ?string $note,
        public readonly InvoiceSupplierData $supplier,
        /** @var InvoiceItemData[] */
        #[DataCollectionOf(InvoiceItemData::class)]
        public readonly array $items,
        /** @var VatBreakdownLineData[] */
        #[DataCollectionOf(VatBreakdownLineData::class)]
        public readonly array $vat_breakdown,
        public readonly bool $qr_available,
        public readonly ?string $qr_data_uri = null,
        /** @var string[] */
        public readonly array $supplier_missing_fields = [],
    ) {}

    public static function fromModel(Invoice $invoice, ?string $qrDataUri): self
    {
        $invoice->loadMissing(['items', 'creditNote']);

        $supplierMissingFields = $invoice->isEditable()
            ? $invoice->loadMissing('tenant')->tenant?->missingSupplierFields() ?? []
            : [];

        return new self(
            id: $invoice->id,
            client_id: $invoice->client_id,
            client_name: $invoice->client?->name,
            cleaning_object_id: $invoice->cleaning_object_id,
            credited_invoice_id: $invoice->credited_invoice_id,
            credit_note_id: $invoice->creditNote?->id,
            recurring_invoice_id: $invoice->recurring_invoice_id,
            type: $invoice->type,
            status: $invoice->status,
            template: $invoice->template,
            number: $invoice->number,
            variable_symbol: $invoice->variable_symbol,
            period_from: $invoice->period_from?->toDateString(),
            period_to: $invoice->period_to?->toDateString(),
            issue_date: $invoice->issue_date->toDateString(),
            delivery_date: $invoice->delivery_date->toDateString(),
            due_date: $invoice->due_date->toDateString(),
            issued_at: $invoice->issued_at?->toIso8601String(),
            sent_at: $invoice->sent_at?->toIso8601String(),
            paid_at: $invoice->paid_at?->toIso8601String(),
            cancelled_at: $invoice->cancelled_at?->toIso8601String(),
            is_vat_payer: $invoice->is_vat_payer,
            vat_rate: $invoice->vat_rate,
            subtotal: $invoice->subtotal,
            vat_amount: $invoice->vat_amount,
            total: $invoice->total,
            deposit: $invoice->deposit,
            balance_due: (string) $invoice->balance_due,
            rounding_amount: $invoice->rounding_amount,
            payment_type: $invoice->payment_type,
            currency: $invoice->currency,
            rounding_mode: $invoice->rounding_mode,
            constant_symbol: $invoice->constant_symbol,
            specific_symbol: $invoice->specific_symbol,
            header_text: $invoice->header_text,
            footer_text: $invoice->footer_text,
            customer_name: $invoice->customer_name,
            customer_representative: $invoice->customer_representative,
            customer_ico: $invoice->customer_ico,
            customer_dic: $invoice->customer_dic,
            customer_vat_number: $invoice->customer_vat_number,
            customer_street: $invoice->customer_street,
            customer_city: $invoice->customer_city,
            customer_postal_code: $invoice->customer_postal_code,
            customer_country: $invoice->customer_country,
            customer_email: $invoice->customer_email,
            object_name: $invoice->object_name,
            object_street: $invoice->object_street,
            object_city: $invoice->object_city,
            object_postal_code: $invoice->object_postal_code,
            note: $invoice->note,
            supplier: InvoiceSupplierData::fromInvoice($invoice),
            items: $invoice->items->map(fn (InvoiceItem $item) => InvoiceItemData::fromModel($item))->all(),
            vat_breakdown: array_map(fn (array $l) => VatBreakdownLineData::from($l), $invoice->vat_breakdown ?? []),
            qr_available: $invoice->status !== InvoiceStatusEnum::Draft
                && $invoice->supplier_iban !== null
                && $invoice->variable_symbol !== null,
            qr_data_uri: $qrDataUri,
            supplier_missing_fields: $supplierMissingFields,
        );
    }
}
