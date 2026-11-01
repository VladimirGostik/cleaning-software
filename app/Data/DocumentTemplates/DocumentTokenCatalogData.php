<?php

declare(strict_types=1);

namespace App\Data\DocumentTemplates;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DocumentTokenCatalogData extends Data
{
    public function __construct(
        /** @var DocumentTokenData[] */
        #[DataCollectionOf(DocumentTokenData::class)]
        public readonly array $service_agreement,
        /** @var DocumentTokenData[] */
        #[DataCollectionOf(DocumentTokenData::class)]
        public readonly array $employment,
        /** @var DocumentTokenData[] */
        #[DataCollectionOf(DocumentTokenData::class)]
        public readonly array $nda,
        /** @var DocumentTokenData[] */
        #[DataCollectionOf(DocumentTokenData::class)]
        public readonly array $gdpr,
        /** @var DocumentTokenData[] */
        #[DataCollectionOf(DocumentTokenData::class)]
        public readonly array $other,
        /** @var DocumentTokenData[] */
        #[DataCollectionOf(DocumentTokenData::class)]
        public readonly array $quote,
    ) {}
}
