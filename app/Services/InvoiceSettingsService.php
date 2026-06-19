<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Invoices\InvoiceSettingsData;
use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RoundingModeEnum;
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
                'swift_bic' => $data->swift_bic,
                'registration_info' => $data->registration_info,
            ];

            // Only update vat_rate when explicitly provided — column is NOT NULL with DB default 23
            if ($data->vat_rate !== null) {
                $attributes['vat_rate'] = $data->vat_rate;
            }

            $tenant->update($attributes);

            $this->upsertInterface(
                $tenant,
                $data->invoice_template,
                $data->recurring_default_state,
                $data->default_constant_symbol,
                $data->default_payment_type,
                $data->default_currency,
                $data->default_rounding_mode,
            );
        });
    }

    private function upsertInterface(
        Tenant $tenant,
        InvoiceTemplateEnum $template,
        RecurringDefaultStateEnum $recurringDefaultState,
        ?string $defaultConstantSymbol,
        PaymentTypeEnum $defaultPaymentType,
        CurrencyEnum $defaultCurrency,
        RoundingModeEnum $defaultRoundingMode,
    ): void {
        $fields = [
            'invoice_template' => $template,
            'recurring_default_state' => $recurringDefaultState,
            'default_constant_symbol' => $defaultConstantSymbol,
            'default_payment_type' => $defaultPaymentType,
            'default_currency' => $defaultCurrency,
            'default_rounding_mode' => $defaultRoundingMode,
        ];

        if ($tenant->interface !== null) {
            $tenant->interface->update($fields);
        } else {
            TenantInterface::create(array_merge(['tenant_id' => $tenant->id], $fields));
        }
    }
}
