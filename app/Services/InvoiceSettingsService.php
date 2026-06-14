<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoiceSettingsData;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Models\Tenant;
use App\Models\TenantInterface;
use Illuminate\Database\DatabaseManager;

final readonly class InvoiceSettingsService
{
    public function __construct(private DatabaseManager $db) {}

    public function update(Tenant $tenant, InvoiceSettingsData $data): void
    {
        $this->db->transaction(function () use ($tenant, $data): void {
            $attributes = [
                'invoice_number_format' => $data->invoice_number_format,
                'iban' => $data->iban,
                'registration_info' => $data->registration_info,
            ];

            // Only update vat_rate when explicitly provided — column is NOT NULL with DB default 23
            if ($data->vat_rate !== null) {
                $attributes['vat_rate'] = $data->vat_rate;
            }

            $tenant->update($attributes);

            $this->upsertInterface($tenant, $data->invoice_template, $data->recurring_default_state);
        });
    }

    private function upsertInterface(
        Tenant $tenant,
        InvoiceTemplateEnum $template,
        RecurringDefaultStateEnum $recurringDefaultState,
    ): void {
        if ($tenant->interface !== null) {
            $tenant->interface->update([
                'invoice_template' => $template,
                'recurring_default_state' => $recurringDefaultState,
            ]);
        } else {
            TenantInterface::create([
                'tenant_id' => $tenant->id,
                'invoice_template' => $template,
                'recurring_default_state' => $recurringDefaultState,
            ]);
        }
    }
}
