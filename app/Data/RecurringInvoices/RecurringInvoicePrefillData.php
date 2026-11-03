<?php

declare(strict_types=1);

namespace App\Data\RecurringInvoices;

use App\Enums\CurrencyEnum;
use App\Enums\RecurringFrequencyEnum;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RecurringInvoicePrefillData extends Data
{
    public function __construct(
        public readonly string $quote_id,
        public readonly ?string $client_id,
        public readonly ?string $cleaning_object_id,
        public readonly string $name,
        public readonly RecurringFrequencyEnum $frequency,
        public readonly ?string $customer_name,
        public readonly ?string $customer_email,
        public readonly ?string $customer_street,
        public readonly ?string $customer_city,
        public readonly ?string $customer_postal_code,
        public readonly CurrencyEnum $currency,
        public readonly ?string $note,
        /** @var RecurringInvoiceItemData[] */
        #[DataCollectionOf(RecurringInvoiceItemData::class)]
        public readonly array $items,
    ) {}
}
