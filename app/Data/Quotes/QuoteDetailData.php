<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Data\Invoices\VatBreakdownLineData;
use App\Data\Media\MediaFileData;
use App\Enums\CurrencyEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\Quote;
use App\Models\QuoteItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteDetailData extends Data
{
    public function __construct(
        public string $id,
        public ?string $client_id,
        public ?string $cleaning_object_id,
        public QuoteStatusEnum $status,
        public QuoteKindEnum $kind,
        public ?string $number,
        public ?string $subject,
        public string $issue_date,
        public string $valid_until,
        public ?string $sent_at,
        public ?string $accepted_at,
        public ?string $rejected_at,
        public bool $is_vat_payer,
        public ?string $vat_rate,
        public CurrencyEnum $currency,
        public string $subtotal,
        public string $vat_amount,
        public string $total,
        public ?string $note,
        public string $customer_name,
        public ?string $customer_email,
        public ?string $customer_street,
        public ?string $customer_city,
        public ?string $customer_postal_code,
        public ?string $object_name,
        /** @var QuoteItemData[] */
        #[DataCollectionOf(QuoteItemData::class)]
        public array $items,
        /** @var VatBreakdownLineData[] */
        #[DataCollectionOf(VatBreakdownLineData::class)]
        public array $vat_breakdown,
        public ?MediaFileData $document,
    ) {}

    public static function fromModel(Quote $quote): self
    {
        $quote->loadMissing(['items', 'client', 'cleaningObject', 'media']);

        $document = $quote->getFirstMedia('document');

        return new self(
            id: $quote->id,
            client_id: $quote->client_id,
            cleaning_object_id: $quote->cleaning_object_id,
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
            customer_name: $quote->client?->name ?? $quote->customer_name ?? '',
            customer_email: $quote->customer_email,
            customer_street: $quote->customer_street,
            customer_city: $quote->customer_city,
            customer_postal_code: $quote->customer_postal_code,
            object_name: $quote->cleaningObject?->name,
            items: $quote->items->map(fn (QuoteItem $item) => QuoteItemData::fromModel($item))->all(),
            vat_breakdown: array_map(fn (array $l) => VatBreakdownLineData::from($l), $quote->vat_breakdown ?? []),
            document: $document !== null ? MediaFileData::fromMedia($document, route('quotes.pdf', $quote)) : null,
        );
    }
}
