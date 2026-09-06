<?php

declare(strict_types=1);

namespace App\Data\Dashboard;

use App\Enums\CurrencyEnum;
use App\Enums\DashboardAlertTypeEnum;
use App\Enums\TenantColorEnum;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\ScheduledJob;
use App\Models\Tenant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DashboardAlertData extends Data
{
    public function __construct(
        public readonly DashboardAlertTypeEnum $type,
        public readonly string $tenant_id,
        public readonly string $tenant_name,
        public readonly ?TenantColorEnum $tenant_color,
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly ?string $amount,
        public readonly ?CurrencyEnum $currency,
        public readonly ?string $due_date,
        public readonly ?int $days,
        public readonly string $url,
    ) {}

    public static function fromInvoice(Invoice $invoice, Tenant $tenant): self
    {
        return new self(
            type: DashboardAlertTypeEnum::OverdueInvoice,
            tenant_id: $tenant->id,
            tenant_name: $tenant->name,
            tenant_color: $tenant->interface?->color,
            title: $invoice->number ?? $invoice->customer_name,
            subtitle: $invoice->customer_name,
            amount: $invoice->total,
            currency: $invoice->currency,
            due_date: $invoice->due_date->toDateString(),
            days: max((int) $invoice->due_date->diffInDays(today()), 0),
            url: route('invoices.show', $invoice->id, absolute: false),
        );
    }

    public static function fromContract(Contract $contract, Tenant $tenant): self
    {
        $days = $contract->end_date !== null ? (int) today()->diffInDays($contract->end_date) : 0;

        return new self(
            type: DashboardAlertTypeEnum::ContractExpiring,
            tenant_id: $tenant->id,
            tenant_name: $tenant->name,
            tenant_color: $tenant->interface?->color,
            title: $contract->title,
            subtitle: $contract->contractableLabel(),
            amount: null,
            currency: null,
            due_date: $contract->end_date?->toDateString(),
            days: max($days, 0),
            url: route('contracts.show', $contract->id, absolute: false),
        );
    }

    public static function fromQuote(Quote $quote, Tenant $tenant): self
    {
        return new self(
            type: DashboardAlertTypeEnum::QuoteExpiring,
            tenant_id: $tenant->id,
            tenant_name: $tenant->name,
            tenant_color: $tenant->interface?->color,
            title: $quote->number ?? $quote->subject ?? '',
            subtitle: $quote->customer_name,
            amount: $quote->total,
            currency: $quote->currency,
            due_date: $quote->valid_until->toDateString(),
            days: max((int) today()->diffInDays($quote->valid_until), 0),
            url: route('quotes.show', $quote->id, absolute: false),
        );
    }

    public static function fromJob(ScheduledJob $job, Tenant $tenant): self
    {
        return new self(
            type: DashboardAlertTypeEnum::UnassignedJob,
            tenant_id: $tenant->id,
            tenant_name: $tenant->name,
            tenant_color: $tenant->interface?->color,
            title: $job->cleaningObject->name ?? '',
            subtitle: $job->cleaningObject?->client?->name,
            amount: null,
            currency: null,
            due_date: $job->scheduled_date->toDateString(),
            days: max((int) today()->diffInDays($job->scheduled_date), 0),
            url: route('jobs.show', $job->id, absolute: false),
        );
    }

    /**
     * Maps `Tenant::missingSupplierFields()` column vocabulary to the app.json label keys
     * used by the FE `SupplierIncompleteAlert` field list (kept in sync manually).
     *
     * @var array<string, string>
     */
    private const array SUPPLIER_FIELD_LABEL_KEYS = [
        'name' => 'name',
        'address_line' => 'street',
        'city' => 'city',
        'postal_code' => 'postal_code',
        'ico' => 'client_ico',
        'dic' => 'client_dic',
        'vat_number' => 'client_vat_number',
    ];

    public static function supplierIncomplete(Tenant $tenant, string $url): self
    {
        $fields = implode(', ', array_map(
            fn (string $field): string => __('app.'.(self::SUPPLIER_FIELD_LABEL_KEYS[$field] ?? $field)),
            $tenant->missingSupplierFields(),
        ));

        return new self(
            type: DashboardAlertTypeEnum::SupplierIncomplete,
            tenant_id: $tenant->id,
            tenant_name: $tenant->name,
            tenant_color: $tenant->interface?->color,
            title: __('app.dashboard_alert_supplier_incomplete_title'),
            subtitle: __('app.dashboard_alert_supplier_incomplete_subtitle', ['fields' => $fields]),
            amount: null,
            currency: null,
            due_date: null,
            days: null,
            url: $url,
        );
    }
}
