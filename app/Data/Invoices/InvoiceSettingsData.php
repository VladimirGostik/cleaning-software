<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringDefaultStateEnum;
use App\Enums\RoundingModeEnum;
use App\Models\Tenant;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class InvoiceSettingsData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        #[Nullable, Max(20)]
        public readonly ?string $ico,
        #[Nullable, Max(20)]
        public readonly ?string $dic,
        #[Nullable, Max(20)]
        public readonly ?string $vat_number,
        public readonly bool $is_vat_payer,
        #[Nullable, Max(255)]
        public readonly ?string $address_line,
        #[Nullable, Max(255)]
        public readonly ?string $city,
        #[Nullable, Max(16)]
        public readonly ?string $postal_code,
        #[Required, Size(2)]
        public readonly string $country,
        #[Nullable, Email, Max(255)]
        public readonly ?string $contact_email,
        #[Nullable, Max(30)]
        public readonly ?string $contact_phone,
        public readonly InvoiceTemplateEnum $invoice_template,
        #[Regex('/\{X+\}/')]
        #[Max(100)]
        public readonly string $invoice_number_format,
        #[Nullable]
        #[Max(34)]
        #[Regex('/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/')]
        public readonly ?string $iban,
        #[Nullable]
        public readonly ?float $vat_rate,
        #[Nullable]
        #[Max(255)]
        public readonly ?string $registration_info,
        public readonly RecurringDefaultStateEnum $recurring_default_state,
        #[Nullable]
        #[Max(11)]
        #[Regex('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/')]
        public readonly ?string $swift_bic,
        #[Nullable]
        #[Max(10)]
        #[Regex('/^\d*$/')]
        public readonly ?string $default_constant_symbol,
        public readonly PaymentTypeEnum $default_payment_type = PaymentTypeEnum::Transfer,
        public readonly CurrencyEnum $default_currency = CurrencyEnum::EUR,
        public readonly RoundingModeEnum $default_rounding_mode = RoundingModeEnum::None,
    ) {}

    public static function fromTenant(Tenant $tenant): self
    {
        $interface = $tenant->interface;

        return new self(
            name: $tenant->name,
            ico: $tenant->ico,
            dic: $tenant->dic,
            vat_number: $tenant->vat_number,
            is_vat_payer: (bool) $tenant->is_vat_payer,
            address_line: $tenant->address_line,
            city: $tenant->city,
            postal_code: $tenant->postal_code,
            country: $tenant->country ?? 'SK',
            contact_email: $tenant->contact_email,
            contact_phone: $tenant->contact_phone,
            invoice_template: $interface->invoice_template ?? InvoiceTemplateEnum::Classic,
            invoice_number_format: $tenant->invoice_number_format ?? 'FA-{YYYY}-{XXXX}',
            iban: $tenant->iban,
            vat_rate: $tenant->vat_rate !== null ? (float) $tenant->vat_rate : null,
            registration_info: $tenant->registration_info,
            recurring_default_state: $interface->recurring_default_state ?? RecurringDefaultStateEnum::Draft,
            swift_bic: $tenant->swift_bic,
            default_constant_symbol: $interface?->default_constant_symbol,
            default_payment_type: $interface->default_payment_type ?? PaymentTypeEnum::Transfer,
            default_currency: $interface->default_currency ?? CurrencyEnum::EUR,
            default_rounding_mode: $interface->default_rounding_mode ?? RoundingModeEnum::None,
        );
    }

    /**
     * @return array<string, array<int|string, mixed>>
     */
    public static function rules(): array
    {
        return [
            'invoice_number_format' => ['required', 'string', 'max:100', 'regex:/\{X+\}/'],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'registration_info' => ['nullable', 'string', 'max:255'],
            'recurring_default_state' => ['required', Rule::enum(RecurringDefaultStateEnum::class)],
        ];
    }
}
