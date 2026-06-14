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
        public string $id,
        public string $name,
        public RecurringInvoiceStatusEnum $status,
        public RecurringFrequencyEnum $frequency,
        public ?string $client_id,
        public ?string $customer_name,
        public int $day_of_month,
        public ?string $next_run_at,
        public int $occurrences_generated,
        public ?int $occurrences_limit,
        public bool $auto_issue,
        public string $start_date,
        public ?string $end_date,
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
