<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceStatsData extends Data
{
    public function __construct(
        public InvoiceStatCardData $issued_this_month,
        public InvoiceStatCardData $overdue,
        public InvoiceStatCardData $pending,
        public InvoiceStatCardData $recurring_monthly,
    ) {}

    public static function fromAggregates(
        float $issuedSum, int $issuedCount,
        float $overdueSum, int $overdueCount,
        float $pendingSum, int $pendingCount,
        float $recurringSum, int $recurringCount,
    ): self {
        $fmt = fn (float $v) => number_format($v, 2, '.', '');

        return new self(
            issued_this_month: new InvoiceStatCardData($fmt($issuedSum), $issuedCount),
            overdue: new InvoiceStatCardData($fmt($overdueSum), $overdueCount),
            pending: new InvoiceStatCardData($fmt($pendingSum), $pendingCount),
            recurring_monthly: new InvoiceStatCardData($fmt($recurringSum), $recurringCount),
        );
    }
}
