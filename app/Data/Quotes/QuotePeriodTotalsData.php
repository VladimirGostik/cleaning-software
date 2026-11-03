<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\QuoteBillingPeriodEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuotePeriodTotalsData extends Data
{
    public function __construct(
        public readonly QuoteBillingPeriodEnum $billing_period,
        public readonly string $subtotal,
        public readonly string $vat_amount,
        public readonly string $total,
    ) {}

    /**
     * @param  array<int, array{billing_period: string, subtotal: string, vat_amount: string, total: string}>|null  $rows
     * @return list<self>
     */
    public static function collectFrom(?array $rows): array
    {
        return array_values(array_map(
            static fn (array $row): self => new self(
                billing_period: QuoteBillingPeriodEnum::from($row['billing_period']),
                subtotal: $row['subtotal'],
                vat_amount: $row['vat_amount'],
                total: $row['total'],
            ),
            $rows ?? [],
        ));
    }
}
