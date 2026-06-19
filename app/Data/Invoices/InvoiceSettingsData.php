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
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceSettingsData extends Data
{
    public function __construct(
        public InvoiceTemplateEnum $invoice_template,
        #[Regex('/\{X+\}/')]
        #[Max(100)]
        public string $invoice_number_format,
        #[Nullable]
        #[Max(34)]
        #[Regex('/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/')]
        public ?string $iban,
        #[Nullable]
        public ?float $vat_rate,
        #[Nullable]
        #[Max(255)]
        public ?string $registration_info,
        public RecurringDefaultStateEnum $recurring_default_state,
        #[Nullable]
        #[Max(11)]
        #[Regex('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/')]
        public ?string $swift_bic,
        #[Nullable]
        #[Max(10)]
        #[Regex('/^\d*$/')]
        public ?string $default_constant_symbol,
        // New defaults — 'sometimes' rule auto-applied by Spatie when PHP default is set
        public PaymentTypeEnum $default_payment_type = PaymentTypeEnum::Transfer,
        public CurrencyEnum $default_currency = CurrencyEnum::EUR,
        public RoundingModeEnum $default_rounding_mode = RoundingModeEnum::None,
    ) {}

    public static function fromTenant(Tenant $tenant): self
    {
        return new self(
            invoice_template: $tenant->interface?->invoice_template ?? InvoiceTemplateEnum::Classic,
            invoice_number_format: $tenant->invoice_number_format ?? 'FA-{YYYY}-{XXXX}',
            iban: $tenant->iban,
            vat_rate: $tenant->vat_rate !== null ? (float) $tenant->vat_rate : null,
            registration_info: $tenant->registration_info,
            recurring_default_state: $tenant->interface?->recurring_default_state ?? RecurringDefaultStateEnum::Draft,
            swift_bic: $tenant->swift_bic,
            default_constant_symbol: $tenant->interface?->default_constant_symbol,
            default_payment_type: $tenant->interface?->default_payment_type ?? PaymentTypeEnum::Transfer,
            default_currency: $tenant->interface?->default_currency ?? CurrencyEnum::EUR,
            default_rounding_mode: $tenant->interface?->default_rounding_mode ?? RoundingModeEnum::None,
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
