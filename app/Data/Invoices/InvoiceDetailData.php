<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Contracts\GeneratesPaymentQr;
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
        public string $id,
        public ?string $client_id,
        public ?string $cleaning_object_id,
        public ?string $credited_invoice_id,
        public InvoiceTypeEnum $type,
        public InvoiceStatusEnum $status,
        public InvoiceTemplateEnum $template,
        public ?string $number,
        public ?string $variable_symbol,
        public ?string $period_from,
        public ?string $period_to,
        public string $issue_date,
        public string $delivery_date,
        public string $due_date,
        public ?string $issued_at,
        public ?string $sent_at,
        public ?string $paid_at,
        public ?string $cancelled_at,
        public bool $is_vat_payer,
        public ?string $vat_rate,
        public string $subtotal,
        public string $vat_amount,
        public string $total,
        public string $deposit,
        public string $balance_due,
        public string $rounding_amount,
        public PaymentTypeEnum $payment_type,
        public CurrencyEnum $currency,
        public RoundingModeEnum $rounding_mode,
        public ?string $constant_symbol,
        public ?string $specific_symbol,
        public ?string $header_text,
        public ?string $footer_text,
        public string $customer_name,
        public ?string $customer_representative,
        public ?string $customer_ico,
        public ?string $customer_dic,
        public ?string $customer_vat_number,
        public ?string $customer_street,
        public ?string $customer_city,
        public ?string $customer_postal_code,
        public ?string $customer_country,
        public ?string $customer_email,
        public ?string $object_name,
        public ?string $object_street,
        public ?string $object_city,
        public ?string $object_postal_code,
        public ?string $note,
        public InvoiceSupplierData $supplier,
        /** @var InvoiceItemData[] */
        #[DataCollectionOf(InvoiceItemData::class)]
        public array $items,
        /** @var VatBreakdownLineData[] */
        #[DataCollectionOf(VatBreakdownLineData::class)]
        public array $vat_breakdown,
        public bool $qr_available,
        public ?string $qr_data_uri = null,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        $invoice->loadMissing('items');

        return new self(
            id: $invoice->id,
            client_id: $invoice->client_id,
            cleaning_object_id: $invoice->cleaning_object_id,
            credited_invoice_id: $invoice->credited_invoice_id,
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
            qr_data_uri: app(GeneratesPaymentQr::class)->dataUri($invoice),
        );
    }
}
