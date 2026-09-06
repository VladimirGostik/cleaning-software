<?php

declare(strict_types=1);

namespace App\Data\RecurringInvoices;

use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\RecurringInvoice;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RecurringInvoiceListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly RecurringInvoiceStatusEnum $status,
        public readonly RecurringFrequencyEnum $frequency,
        public readonly ?string $client_id,
        public readonly ?string $customer_name,
        public readonly ?string $customer_display_name,
        public readonly int $day_of_month,
        public readonly ?string $next_run_at,
        public readonly int $occurrences_generated,
        public readonly ?int $occurrences_limit,
        public readonly bool $auto_issue,
        public readonly string $start_date,
        public readonly ?string $end_date,
    ) {}

    public static function fromModel(RecurringInvoice $ri): self
    {
        return new self(
            id: $ri->id,
            name: $ri->name,
            status: $ri->status,
            frequency: $ri->frequency,
            client_id: $ri->client_id,
            customer_name: $ri->customer_name,
            customer_display_name: $ri->client->name ?? $ri->cleaningObject?->client->name ?? $ri->customer_name,
            day_of_month: $ri->day_of_month,
            next_run_at: $ri->next_run_at?->toDateString(),
            occurrences_generated: $ri->occurrences_generated,
            occurrences_limit: $ri->occurrences_limit,
            auto_issue: $ri->auto_issue,
            start_date: $ri->start_date->toDateString(),
            end_date: $ri->end_date?->toDateString(),
        );
    }
}
