<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\QuoteStatusEnum;
use App\Models\Quote;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteListItemData extends Data
{
    public function __construct(
        public string $id,
        public ?string $number,
        public QuoteStatusEnum $status,
        public ?string $subject,
        public string $customer_name,
        public ?string $object_name,
        public string $total,
        public string $issue_date,
        public string $valid_until,
        public string $client_id,
    ) {}

    public static function fromModel(Quote $quote): self
    {
        return new self(
            id: $quote->id,
            number: $quote->number,
            status: $quote->status,
            subject: $quote->subject,
            customer_name: $quote->client?->name ?? '',
            object_name: $quote->cleaningObject?->name,
            total: $quote->total,
            issue_date: $quote->issue_date->toDateString(),
            valid_until: $quote->valid_until->toDateString(),
            client_id: $quote->client_id,
        );
    }
}
