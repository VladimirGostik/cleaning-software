<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Data\Clients\ClientOptionData;
use App\Data\Objects\ObjectOptionData;
use App\Enums\CurrencyEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RoundingModeEnum;
use App\Models\Tenant;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceFormContextData extends Data
{
    public function __construct(
        /** @var ClientOptionData[] */
        #[DataCollectionOf(ClientOptionData::class)]
        public readonly array $clients,
        /** @var ObjectOptionData[] */
        #[DataCollectionOf(ObjectOptionData::class)]
        public readonly array $objects,
        public readonly bool $is_vat_payer,
        public readonly ?string $vat_rate,
        /** @var float[] */
        public readonly array $vat_rate_options,
        public readonly InvoiceDefaultsData $defaults,
        public readonly RecurringDefaultStateEnum $recurring_default_state,
        /** @var string[] */
        public readonly array $supplier_missing_fields,
    ) {}

    /**
     * @param  array<int, ClientOptionData>  $clients
     * @param  array<int, ObjectOptionData>  $objects
     */
    public static function fromTenant(Tenant $tenant, array $clients, array $objects): self
    {
        $interface = $tenant->interface;

        /** @var array<int, float> $vatRateOptions */
        $vatRateOptions = array_map(
            static fn (mixed $rate): float => is_numeric($rate) ? (float) $rate : 0.0,
            (array) config('invoicing.vat_rates', []),
        );

        return new self(
            clients: $clients,
            objects: $objects,
            is_vat_payer: (bool) $tenant->is_vat_payer,
            vat_rate: $tenant->vat_rate !== null ? (string) $tenant->vat_rate : null,
            vat_rate_options: $vatRateOptions,
            defaults: new InvoiceDefaultsData(
                constant_symbol: $interface?->default_constant_symbol,
                payment_type: $interface->default_payment_type ?? PaymentTypeEnum::Transfer,
                currency: $interface->default_currency ?? CurrencyEnum::EUR,
                rounding_mode: $interface->default_rounding_mode ?? RoundingModeEnum::None,
            ),
            recurring_default_state: $interface->recurring_default_state ?? RecurringDefaultStateEnum::Draft,
            supplier_missing_fields: $tenant->missingSupplierFields(),
        );
    }
}
