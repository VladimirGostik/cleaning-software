<?php

declare(strict_types=1);

namespace App\Data\RecurringInvoices;

use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RecurringInvoiceDetailData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public RecurringInvoiceStatusEnum $status,
        public RecurringFrequencyEnum $frequency,
        public InvoiceTypeEnum $type,
        public ?InvoiceTemplateEnum $template,
        public ?string $client_id,
        public ?string $cleaning_object_id,
        public int $day_of_month,
        public bool $auto_issue,
        public string $start_date,
        public ?string $end_date,
        public ?int $occurrences_limit,
        public int $occurrences_generated,
        public ?string $next_run_at,
        public ?string $last_generated_at,
        public int $due_days,
        public ?string $period_from,
        public ?string $period_to,
        public ?string $customer_name,
        public ?string $customer_representative,
        public ?string $customer_ico,
        public ?string $customer_dic,
        public ?string $customer_vat_number,
        public ?string $customer_street,
        public ?string $customer_city,
        public ?string $customer_postal_code,
        public ?string $customer_country,
        public ?string $customer_email,
        public ?string $note,
        /** @var RecurringInvoiceItemData[] */
        #[DataCollectionOf(RecurringInvoiceItemData::class)]
        public array $items,
    ) {}

    public static function fromModel(RecurringInvoice $ri): self
    {
        $ri->loadMissing('items');

        $items = [];
        /** @var RecurringInvoiceItem $item */
        foreach ($ri->items as $item) {
            $items[] = new RecurringInvoiceItemData(
                description: $item->description,
                quantity: (float) $item->quantity,
                unit: $item->unit,
                unit_price: (float) $item->unit_price,
            );
        }

        return new self(
            id: $ri->id,
            name: $ri->name,
            status: $ri->status,
            frequency: $ri->frequency,
            type: $ri->type,
            template: $ri->template,
            client_id: $ri->client_id,
            cleaning_object_id: $ri->cleaning_object_id,
            day_of_month: $ri->day_of_month,
            auto_issue: $ri->auto_issue,
            start_date: $ri->start_date->toDateString(),
            end_date: $ri->end_date?->toDateString(),
            occurrences_limit: $ri->occurrences_limit,
            occurrences_generated: $ri->occurrences_generated,
            next_run_at: $ri->next_run_at?->toDateString(),
            last_generated_at: $ri->last_generated_at?->toIso8601String(),
            due_days: $ri->due_days,
            period_from: $ri->period_from?->toDateString(),
            period_to: $ri->period_to?->toDateString(),
            customer_name: $ri->customer_name,
            customer_representative: $ri->customer_representative,
            customer_ico: $ri->customer_ico,
            customer_dic: $ri->customer_dic,
            customer_vat_number: $ri->customer_vat_number,
            customer_street: $ri->customer_street,
            customer_city: $ri->customer_city,
            customer_postal_code: $ri->customer_postal_code,
            customer_country: $ri->customer_country,
            customer_email: $ri->customer_email,
            note: $ri->note,
            items: $items,
        );
    }
}
