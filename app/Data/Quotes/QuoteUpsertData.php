<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\CurrencyEnum;
use App\Enums\QuoteKindEnum;
use App\Models\CleaningObject;
use App\Models\Quote;
use Closure;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteUpsertData extends Data
{
    public function __construct(
        #[Nullable]
        public ?string $client_id,
        #[Nullable]
        public ?string $cleaning_object_id,
        #[Nullable]
        public ?string $subject,
        #[Required]
        public string $issue_date,
        #[Required]
        public string $valid_until,
        #[Nullable]
        public ?string $note,
        /** @var QuoteItemData[] */
        #[DataCollectionOf(QuoteItemData::class)]
        public array $items,
        #[Nullable]
        public ?string $customer_name = null,
        #[Nullable]
        public ?string $customer_email = null,
        #[Nullable]
        public ?string $customer_street = null,
        #[Nullable]
        public ?string $customer_city = null,
        #[Nullable]
        public ?string $customer_postal_code = null,
        #[Nullable, Max(50)]
        public ?string $number = null,
        public QuoteKindEnum $kind = QuoteKindEnum::Itemized,
        public CurrencyEnum $currency = CurrencyEnum::EUR,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
        $routeQuote = request()->route('quote');
        $isDocument = request()->input('kind') === QuoteKindEnum::Document->value;

        return [
            'client_id' => [
                'nullable',
                'string',
                'prohibits:customer_name,customer_email,customer_street,customer_city,customer_postal_code',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
            ],
            'cleaning_object_id' => [
                'nullable',
                'string',
                Rule::exists('objects', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                self::objectBelongsToClientRule(),
            ],
            'number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('quotes', 'number')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at'))
                    ->ignore($routeQuote),
            ],
            'customer_name' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_street' => ['nullable', 'string', 'max:255'],
            'customer_city' => ['nullable', 'string', 'max:255'],
            'customer_postal_code' => ['nullable', 'string', 'max:16'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'kind' => [
                'required',
                Rule::enum(QuoteKindEnum::class),
                // D7 — immutable after create
                ...($routeQuote instanceof Quote ? [Rule::in([$routeQuote->kind->value])] : []),
            ],
            'items' => ['array', Rule::when($isDocument, ['prohibited'], ['required', 'min:1'])],
        ];
    }

    public static function objectBelongsToClientRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
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
        };
    }
}
