<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Data\Invoices\VatBreakdownLineData;
use App\Data\MediaFileData;
use App\Enums\CurrencyEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteDetailData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $client_id,
        public readonly ?string $client_name,
        public readonly ?string $cleaning_object_id,
        public readonly ?string $object_name,
        public readonly QuoteStatusEnum $status,
        public readonly QuoteKindEnum $kind,
        public readonly ?string $number,
        public readonly ?string $subject,
        public readonly string $issue_date,
        public readonly string $valid_until,
        public readonly ?string $sent_at,
        public readonly ?string $accepted_at,
        public readonly ?string $rejected_at,
        public readonly bool $is_vat_payer,
        public readonly ?string $vat_rate,
        public readonly CurrencyEnum $currency,
        public readonly string $subtotal,
        public readonly string $vat_amount,
        public readonly string $total,
        public readonly ?string $note,
        public readonly string $customer_name,
        public readonly ?string $customer_email,
        public readonly ?string $customer_street,
        public readonly ?string $customer_city,
        public readonly ?string $customer_postal_code,
        /** @var QuoteItemData[] */
        #[DataCollectionOf(QuoteItemData::class)]
        public readonly array $items,
        /** @var VatBreakdownLineData[] */
        #[DataCollectionOf(VatBreakdownLineData::class)]
        public readonly array $vat_breakdown,
        public readonly ?MediaFileData $document,
        /** @var QuoteInvoiceLinkData[] */
        #[DataCollectionOf(QuoteInvoiceLinkData::class)]
        public readonly array $invoices,
    ) {}

    public static function fromModel(Quote $quote): self
    {
        $quote->loadMissing(['items', 'client', 'cleaningObject', 'media', 'invoices']);

        $media = $quote->getFirstMedia('document');

        return new self(
            id: $quote->id,
            client_id: $quote->client_id,
            client_name: $quote->client?->name,
            cleaning_object_id: $quote->cleaning_object_id,
            object_name: $quote->cleaningObject?->name,
            status: $quote->status,
            kind: $quote->kind,
            number: $quote->number,
            subject: $quote->subject,
            issue_date: $quote->issue_date->toDateString(),
            valid_until: $quote->valid_until->toDateString(),
            sent_at: $quote->sent_at?->toIso8601String(),
            accepted_at: $quote->accepted_at?->toIso8601String(),
            rejected_at: $quote->rejected_at?->toIso8601String(),
            is_vat_payer: $quote->is_vat_payer,
            vat_rate: $quote->vat_rate,
            currency: $quote->currency,
            subtotal: $quote->subtotal,
            vat_amount: $quote->vat_amount,
            total: $quote->total,
            note: $quote->note,
            customer_name: $quote->client->name ?? $quote->customer_name ?? '',
            customer_email: $quote->customer_email,
            customer_street: $quote->customer_street,
            customer_city: $quote->customer_city,
            customer_postal_code: $quote->customer_postal_code,
            items: $quote->items->map(fn (QuoteItem $item) => QuoteItemData::fromModel($item))->all(),
            vat_breakdown: array_map(fn (array $l) => VatBreakdownLineData::from($l), $quote->vat_breakdown ?? []),
            document: $media !== null ? MediaFileData::fromMedia($media, route('quotes.pdf', $quote)) : null,
            invoices: $quote->invoices->map(fn (Invoice $invoice) => QuoteInvoiceLinkData::fromModel($invoice))->all(),
        );
    }
}
