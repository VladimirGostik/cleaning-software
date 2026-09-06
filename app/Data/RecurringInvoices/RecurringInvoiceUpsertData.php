<?php

declare(strict_types=1);

namespace App\Data\RecurringInvoices;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RoundingModeEnum;
use App\Rules\ObjectBelongsToClient;
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
final class RecurringInvoiceUpsertData extends Data
{
    public function __construct(
        #[Nullable]
        public readonly ?string $client_id,
        #[Nullable]
        public readonly ?string $cleaning_object_id,
        #[Required]
        public readonly string $name,
        #[Required]
        public readonly InvoiceTypeEnum $type,
        #[Nullable]
        public readonly ?InvoiceTemplateEnum $template,
        #[Required]
        public readonly RecurringFrequencyEnum $frequency,
        #[Required]
        public readonly int $day_of_month,
        #[Required]
        public readonly bool $auto_issue,
        #[Required]
        public readonly string $start_date,
        #[Nullable]
        public readonly ?string $end_date,
        #[Nullable]
        public readonly ?int $occurrences_limit,
        #[Required]
        public readonly int $due_days,
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
        /** @var RecurringInvoiceItemData[] */
        #[Required, ArrayType, Min(1)]
        #[DataCollectionOf(RecurringInvoiceItemData::class)]
        public readonly array $items,
        #[Nullable]
        public readonly ?string $constant_symbol,
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
                new ObjectBelongsToClient,
            ],
            'day_of_month' => ['required', 'integer', 'between:1,28'],
            'start_date' => ['required', 'date'],
            'end_date' => [
                'nullable',
                'date',
                'after:start_date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && request()->input('occurrences_limit') !== null) {
                        $fail(__('app.recurring_invoice_end_date_or_limit'));
                    }
                },
            ],
            'occurrences_limit' => ['nullable', 'integer', 'min:1'],
            'due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'customer_name' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'period_from' => ['required_if:type,monthly,special', 'nullable', 'date'],
            'period_to' => ['required_if:type,monthly,special', 'nullable', 'date', 'after_or_equal:period_from'],
            'items' => ['required', 'array', 'min:1'],
            'deposit' => ['numeric', 'min:0'],
            'constant_symbol' => ['nullable', 'string', 'max:10', 'regex:/^\d*$/'],
            'payment_type' => ['required', Rule::enum(PaymentTypeEnum::class)],
            'currency' => ['required', Rule::enum(CurrencyEnum::class)],
            'rounding_mode' => ['required', Rule::enum(RoundingModeEnum::class)],
        ];
    }
}
