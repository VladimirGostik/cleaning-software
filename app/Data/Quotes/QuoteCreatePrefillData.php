<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\QuoteKindEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteCreatePrefillData extends Data
{
    public function __construct(
        public readonly ?QuoteKindEnum $kind,
        public readonly ?string $client_id,
        public readonly ?string $cleaning_object_id,
    ) {}
}
