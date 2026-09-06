<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Data\Clients\ClientOptionData;
use App\Data\Objects\ObjectOptionData;
use App\Enums\CurrencyEnum;
use App\Models\Tenant;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteFormContextData extends Data
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
        public readonly int $default_validity_days,
        /** @var string[] */
        public readonly array $document_allowed_mimes,
        public readonly int $document_max_size_kb,
        public readonly CurrencyEnum $default_currency,
    ) {}

    /**
     * @param  array<int, ClientOptionData>  $clients
     * @param  array<int, ObjectOptionData>  $objects
     */
    public static function fromTenant(Tenant $tenant, array $clients, array $objects): self
    {
        /** @var array<int, float> $vatRateOptions */
        $vatRateOptions = array_map(
            static fn (mixed $rate): float => is_numeric($rate) ? (float) $rate : 0.0,
            (array) config('invoicing.vat_rates', []),
        );

        $defaultValidityDays = config('quotes.default_validity_days', 30);
        $maxSizeKb = config('quotes.document.max_size_kb', 10240);

        /** @var list<string> $allowedMimes */
        $allowedMimes = array_values(array_filter(
            (array) config('quotes.document.allowed_mimes', []),
            static fn (mixed $mime): bool => is_string($mime),
        ));

        return new self(
            clients: $clients,
            objects: $objects,
            is_vat_payer: (bool) $tenant->is_vat_payer,
            vat_rate: $tenant->vat_rate !== null ? (string) $tenant->vat_rate : null,
            vat_rate_options: $vatRateOptions,
            default_validity_days: is_numeric($defaultValidityDays) ? (int) $defaultValidityDays : 30,
            document_allowed_mimes: $allowedMimes,
            document_max_size_kb: is_numeric($maxSizeKb) ? (int) $maxSizeKb : 10240,
            default_currency: $tenant->interface->default_currency ?? CurrencyEnum::EUR,
        );
    }
}
