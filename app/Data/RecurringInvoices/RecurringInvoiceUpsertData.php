<?php

declare(strict_types=1);

namespace App\Data\RecurringInvoices;

use App\Enums\CurrencyEnum;
use App\Enums\InvoiceTemplateEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\PaymentTypeEnum;
use App\Enums\RecurringFrequencyEnum;
use App\Enums\RoundingModeEnum;
use App\Models\CleaningObject;
use Closure;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RecurringInvoiceUpsertData extends Data
{
    public function __construct(
        #[Nullable]
        public ?string $client_id,
        #[Nullable]
        public ?string $cleaning_object_id,
        #[Required]
        public string $name,
        #[Required]
        public InvoiceTypeEnum $type,
        #[Nullable]
        public ?InvoiceTemplateEnum $template,
        #[Required]
        public RecurringFrequencyEnum $frequency,
        #[Required]
        public int $day_of_month,
        #[Required]
        public bool $auto_issue,
        #[Required]
        public string $start_date,
        #[Nullable]
        public ?string $end_date,
        #[Nullable]
        public ?int $occurrences_limit,
        #[Required]
        public int $due_days,
        #[Nullable]
        public ?string $period_from,
        #[Nullable]
        public ?string $period_to,
        #[Nullable]
        public ?string $customer_name,
        #[Nullable]
        public ?string $customer_representative,
        #[Nullable]
        public ?string $customer_ico,
        #[Nullable]
        public ?string $customer_dic,
        #[Nullable]
        public ?string $customer_vat_number,
        #[Nullable]
        public ?string $customer_street,
        #[Nullable]
        public ?string $customer_city,
        #[Nullable]
        public ?string $customer_postal_code,
        #[Nullable]
        public ?string $customer_country,
        #[Nullable]
        public ?string $customer_email,
        #[Nullable]
        public ?string $note,
        /** @var RecurringInvoiceItemData[] */
        #[Required, ArrayType, Min(1)]
        #[DataCollectionOf(RecurringInvoiceItemData::class)]
        public array $items,
        #[Nullable]
        public ?string $constant_symbol,
        #[Nullable]
        public ?string $header_text,
        #[Nullable]
        public ?string $footer_text,
        #[Min(0)]
        public float $deposit = 0,
        public PaymentTypeEnum $payment_type = PaymentTypeEnum::Transfer,
        public CurrencyEnum $currency = CurrencyEnum::EUR,
        public RoundingModeEnum $rounding_mode = RoundingModeEnum::None,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
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
                        $fail('The cleaning object requires a client to be selected.');

                        return;
                    }

                    $exists = CleaningObject::withoutGlobalScopes()
                        ->where('id', $value)
                        ->where('client_id', $clientId)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $exists) {
                        $fail('The selected object does not belong to the selected client.');
                    }
                },
            ],
            'day_of_month' => ['required', 'integer', 'between:1,28'],
            'start_date' => ['required', 'date'],
            'end_date' => [
                'nullable',
                'date',
                'after:start_date',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value !== null && request()->input('occurrences_limit') !== null) {
                        $fail(__('app.recurring_invoices.validation.end_date_or_limit'));
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
