<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\CurrencyEnum;
use App\Enums\QuoteKindEnum;
use App\Enums\QuoteStatusEnum;
use App\Models\Quote;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $number,
        public readonly QuoteStatusEnum $status,
        public readonly QuoteKindEnum $kind,
        public readonly ?string $subject,
        public readonly string $customer_name,
        public readonly ?string $client_id,
        public readonly ?string $object_name,
        public readonly CurrencyEnum $currency,
        public readonly string $total,
        public readonly string $issue_date,
        public readonly string $valid_until,
        public readonly bool $has_document,
    ) {}

    public static function fromModel(Quote $quote): self
    {
        return new self(
            id: $quote->id,
            number: $quote->number,
            status: $quote->status,
            kind: $quote->kind,
            subject: $quote->subject,
            customer_name: $quote->client->name ?? $quote->customer_name ?? '',
            client_id: $quote->client_id,
            object_name: $quote->cleaningObject?->name,
            currency: $quote->currency,
            total: $quote->total,
            issue_date: $quote->issue_date->toDateString(),
            valid_until: $quote->valid_until->toDateString(),
            has_document: $quote->getMedia('document')->isNotEmpty(),
        );
    }
}
