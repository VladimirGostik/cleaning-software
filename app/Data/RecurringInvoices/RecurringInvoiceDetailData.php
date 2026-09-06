<?php

declare(strict_types=1);

namespace App\Data\RecurringInvoices;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RecurringInvoiceStatusEnum;
use App\Enums\RoundingModeEnum;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RecurringInvoiceDetailData extends Data
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
        public readonly InvoiceTypeEnum $type,
        public readonly ?InvoiceTemplateEnum $template,
        public readonly ?string $cleaning_object_id,
        public readonly ?string $last_generated_at,
        public readonly int $due_days,
        public readonly ?string $period_from,
        public readonly ?string $period_to,
        public readonly ?string $customer_representative,
        public readonly ?string $customer_ico,
        public readonly ?string $customer_dic,
        public readonly ?string $customer_vat_number,
        public readonly ?string $customer_street,
        public readonly ?string $customer_city,
        public readonly ?string $customer_postal_code,
        public readonly ?string $customer_country,
        public readonly ?string $customer_email,
        public readonly ?string $note,
        public readonly bool $is_vat_payer,
        public readonly string $deposit,
        public readonly PaymentTypeEnum $payment_type,
        public readonly CurrencyEnum $currency,
        public readonly RoundingModeEnum $rounding_mode,
        public readonly ?string $constant_symbol,
        public readonly ?string $header_text,
        public readonly ?string $footer_text,
        /** @var RecurringInvoiceItemData[] */
        #[DataCollectionOf(RecurringInvoiceItemData::class)]
        public readonly array $items,
    ) {}

    public static function fromModel(RecurringInvoice $ri): self
    {
        $ri->loadMissing(['items', 'client', 'cleaningObject.client', 'tenant']);

        $items = [];
        /** @var RecurringInvoiceItem $item */
        foreach ($ri->items as $item) {
            $items[] = new RecurringInvoiceItemData(
                description: $item->description,
                quantity: (float) $item->quantity,
                unit: $item->unit,
                unit_price: (float) $item->unit_price,
                discount_percent: (float) $item->discount_percent,
                vat_rate: (float) $item->vat_rate,
            );
        }

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
            type: $ri->type,
            template: $ri->template,
            cleaning_object_id: $ri->cleaning_object_id,
            last_generated_at: $ri->last_generated_at?->toIso8601String(),
            due_days: $ri->due_days,
            period_from: $ri->period_from?->toDateString(),
            period_to: $ri->period_to?->toDateString(),
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
            is_vat_payer: (bool) $ri->tenant?->is_vat_payer,
            deposit: $ri->deposit,
            payment_type: $ri->payment_type,
            currency: $ri->currency,
            rounding_mode: $ri->rounding_mode,
            constant_symbol: $ri->constant_symbol,
            header_text: $ri->header_text,
            footer_text: $ri->footer_text,
            items: $items,
        );
    }
}
