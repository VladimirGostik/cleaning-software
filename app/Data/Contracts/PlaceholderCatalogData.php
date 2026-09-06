<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PlaceholderCatalogData extends Data
{
    public function __construct(
        /** @var PlaceholderTokenData[] */
        #[DataCollectionOf(PlaceholderTokenData::class)]
        public readonly array $cleaning_object,
        /** @var PlaceholderTokenData[] */
        #[DataCollectionOf(PlaceholderTokenData::class)]
        public readonly array $tenant_membership,
    ) {}
}
