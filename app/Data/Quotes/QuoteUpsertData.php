<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\CurrencyEnum;
use App\Enums\QuoteKindEnum;
use App\Models\Quote;
use App\Rules\ObjectBelongsToClient;
use App\Rules\OwnedTemporaryMedia;
use App\Rules\TemporaryMediaConstraints;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class QuoteUpsertData extends Data
{
    public function __construct(
        #[Nullable]
        public readonly ?string $client_id,
        #[Nullable]
        public readonly ?string $cleaning_object_id,
        #[Nullable]
        public readonly ?string $subject,
        #[Required]
        public readonly string $issue_date,
        #[Required]
        public readonly string $valid_until,
        #[Nullable]
        public readonly ?string $note,
        /** @var QuoteItemData[] */
        #[DataCollectionOf(QuoteItemData::class)]
        public readonly array $items = [],
        #[Nullable]
        public readonly ?string $customer_name = null,
        #[Nullable]
        public readonly ?string $customer_email = null,
        #[Nullable]
        public readonly ?string $customer_street = null,
        #[Nullable]
        public readonly ?string $customer_city = null,
        #[Nullable]
        public readonly ?string $customer_postal_code = null,
        #[Nullable]
        public readonly ?string $number = null,
        #[Nullable]
        public readonly ?string $document_uuid = null,
        public readonly QuoteKindEnum $kind = QuoteKindEnum::Itemized,
        public readonly CurrencyEnum $currency = CurrencyEnum::EUR,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        /** @var string|null $tenantId */
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
        $routeQuote = request()->route('quote');
        $isDocument = request()->input('kind') === QuoteKindEnum::Document->value;
        $maxSizeKb = config('quotes.document.max_size_kb', 10240);

        /** @var list<string> $allowedMimes */
        $allowedMimes = array_values(array_filter(
            (array) config('quotes.document.allowed_mimes', []),
            static fn (mixed $mime): bool => is_string($mime),
        ));

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
                new ObjectBelongsToClient,
            ],
            'number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('quotes', 'number')
                    ->where(fn (Builder $q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at'))
                    ->ignore($routeQuote instanceof Quote ? $routeQuote->id : null),
            ],
            'customer_name' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_street' => ['nullable', 'string', 'max:255'],
            'customer_city' => ['nullable', 'string', 'max:255'],
            'customer_postal_code' => ['nullable', 'string', 'max:16'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'kind' => [
                'required',
                Rule::enum(QuoteKindEnum::class),
                ...($routeQuote instanceof Quote ? [Rule::in([$routeQuote->kind->value])] : []),
            ],
            'items' => Rule::when($isDocument, ['prohibited'], ['required', 'array', 'min:1']),
            'document_uuid' => Rule::when(
                $isDocument,
                [
                    $routeQuote instanceof Quote ? 'nullable' : 'required',
                    'uuid',
                    new OwnedTemporaryMedia,
                    new TemporaryMediaConstraints(
                        $allowedMimes,
                        is_numeric($maxSizeKb) ? (int) $maxSizeKb : 10240,
                    ),
                ],
                ['prohibited'],
            ),
            'currency' => ['required', Rule::enum(CurrencyEnum::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'kind.in' => __('app.quote_kind_immutable'),
        ];
    }
}
