<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use App\Enums\CurrencyEnum;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceMetricsData extends Data
{
    public function __construct(
        public readonly CurrencyEnum $default_currency,
        /** @var InvoiceCurrencyMetricsData[] */
        #[DataCollectionOf(InvoiceCurrencyMetricsData::class)]
        public readonly array $by_currency,
    ) {}
}
