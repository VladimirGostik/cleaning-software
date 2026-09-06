<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RoundingModeEnum;
use App\Models\CleaningObject;
use Closure;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class InvoiceUpsertData extends Data
{
    public function __construct(
        #[Nullable]
        public readonly ?string $client_id,
        #[Nullable]
        public readonly ?string $cleaning_object_id,
        #[Required]
        public readonly InvoiceTypeEnum $type,
        #[Nullable]
        public readonly ?InvoiceTemplateEnum $template,
        #[Required]
        public readonly string $issue_date,
        #[Required]
        public readonly string $delivery_date,
        #[Required]
        public readonly string $due_date,
        #[Nullable]
        public readonly ?string $period_from,
        #[Nullable]
        public readonly ?string $period_to,
        #[Nullable]
        public readonly ?string $customer_name,
        #[Nullable]
        public readonly ?string $customer_representative,
        #[Nullable]
        public readonly ?string $customer_ico,
        #[Nullable]
        public readonly ?string $customer_dic,
        #[Nullable]
        public readonly ?string $customer_vat_number,
        #[Nullable]
        public readonly ?string $customer_street,
        #[Nullable]
        public readonly ?string $customer_city,
        #[Nullable]
        public readonly ?string $customer_postal_code,
        #[Nullable]
        public readonly ?string $customer_country,
        #[Nullable]
        public readonly ?string $customer_email,
        #[Nullable]
        public readonly ?string $note,
        /** @var InvoiceItemData[] */
        #[Required, ArrayType, Min(1)]
        #[DataCollectionOf(InvoiceItemData::class)]
        public readonly array $items,
        #[Nullable]
        public readonly ?string $constant_symbol,
        #[Nullable]
        public readonly ?string $specific_symbol,
        #[Nullable]
        public readonly ?string $header_text,
        #[Nullable]
        public readonly ?string $footer_text,
        #[Min(0)]
        public readonly float $deposit = 0,
        public readonly PaymentTypeEnum $payment_type = PaymentTypeEnum::Transfer,
        public readonly CurrencyEnum $currency = CurrencyEnum::EUR,
        public readonly RoundingModeEnum $rounding_mode = RoundingModeEnum::None,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        /** @var string|null $tenantId */
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'client_id' => [
                'nullable',
                'string',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'cleaning_object_id' => [
                'nullable',
                'string',
                Rule::exists('objects', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $clientId = request()->input('client_id');
                    if ($clientId === null) {
                        $fail(__('app.invoice_object_requires_client'));

                        return;
                    }

                    $exists = CleaningObject::withoutGlobalScopes()
                        ->where('id', $value)
                        ->where('client_id', $clientId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $exists) {
                        $fail(__('app.invoice_object_not_of_client'));
                    }
                },
            ],
            'customer_name' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'period_from' => ['required_if:type,monthly,special', 'nullable', 'date'],
            'period_to' => ['required_if:type,monthly,special', 'nullable', 'date', 'after_or_equal:period_from'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'delivery_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'deposit' => ['numeric', 'min:0'],
            'constant_symbol' => ['nullable', 'string', 'max:10', 'regex:/^\d*$/'],
            'specific_symbol' => ['nullable', 'string', 'max:10', 'regex:/^\d*$/'],
            'payment_type' => ['required', Rule::enum(PaymentTypeEnum::class)],
            'currency' => ['required', Rule::enum(CurrencyEnum::class)],
            'rounding_mode' => ['required', Rule::enum(RoundingModeEnum::class)],
        ];
    }
}
