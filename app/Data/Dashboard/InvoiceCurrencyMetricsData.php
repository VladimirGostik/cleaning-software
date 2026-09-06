<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use App\Enums\CurrencyEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceCurrencyMetricsData extends Data
{
    public function __construct(
        public readonly CurrencyEnum $currency,
        public readonly int $invoiced_month_count,
        public readonly string $invoiced_month_sum,
        public readonly int $unpaid_count,
        public readonly string $unpaid_sum,
        public readonly int $overdue_count,
        public readonly string $overdue_sum,
    ) {}

    public static function fromRow(
        CurrencyEnum $currency,
        int $invoicedMonthCount,
        float $invoicedMonthSum,
        int $unpaidCount,
        float $unpaidSum,
        int $overdueCount,
        float $overdueSum,
    ): self {
        $fmt = fn (float $value): string => number_format($value, 2, '.', '');

        return new self(
            currency: $currency,
            invoiced_month_count: $invoicedMonthCount,
            invoiced_month_sum: $fmt($invoicedMonthSum),
            unpaid_count: $unpaidCount,
            unpaid_sum: $fmt($unpaidSum),
            overdue_count: $overdueCount,
            overdue_sum: $fmt($overdueSum),
        );
    }

    public static function zero(CurrencyEnum $currency): self
    {
        return new self($currency, 0, '0.00', 0, '0.00', 0, '0.00');
    }
}
