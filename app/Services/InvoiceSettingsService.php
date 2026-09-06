<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoiceSettingsData;
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
                'name' => $data->name,
                'ico' => $data->ico,
                'dic' => $data->dic,
                'vat_number' => $data->vat_number,
                'is_vat_payer' => $data->is_vat_payer,
                'address_line' => $data->address_line,
                'city' => $data->city,
                'postal_code' => $data->postal_code,
                'country' => $data->country,
                'contact_email' => $data->contact_email,
                'contact_phone' => $data->contact_phone,
                'invoice_number_format' => $data->invoice_number_format,
                'iban' => $data->iban,
                'swift_bic' => $data->swift_bic,
                'registration_info' => $data->registration_info,
            ];

            // Only update vat_rate when explicitly provided — column is NOT NULL with DB default 23
            if ($data->vat_rate !== null) {
                $attributes['vat_rate'] = $data->vat_rate;
            }

            $tenant->update($attributes);

            $interfaceFields = [
                'invoice_template' => $data->invoice_template,
                'recurring_default_state' => $data->recurring_default_state,
                'default_constant_symbol' => $data->default_constant_symbol,
                'default_payment_type' => $data->default_payment_type,
                'default_currency' => $data->default_currency,
                'default_rounding_mode' => $data->default_rounding_mode,
            ];

            if ($tenant->interface !== null) {
                $tenant->interface->update($interfaceFields);
            } else {
                TenantInterface::create(array_merge(['tenant_id' => $tenant->id], $interfaceFields));
            }
        });
    }
}
