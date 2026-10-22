<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use App\Data\Contracts\PlaceholderTokenData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceMailPlaceholderCatalogData extends Data
{
    /**
     * @param  list<PlaceholderTokenData>  $tokens
     */
    public function __construct(
        #[DataCollectionOf(PlaceholderTokenData::class)]
        public readonly array $tokens,
    ) {}
}
