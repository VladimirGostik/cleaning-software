<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\QuoteStatusEnum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteIndexFilterData extends Data
{
    public function __construct(
        #[Nullable, Max(255)]
        public ?string $search = null,
        #[Nullable]
        public ?QuoteStatusEnum $status = null,
        #[Nullable]
        public ?string $client_id = null,
        #[Nullable, Rule('date_format:Y-m-d')]
        public ?string $valid_from = null,
        #[Nullable, Rule('date_format:Y-m-d')]
        public ?string $valid_to = null,
        #[Nullable, Min(1), Max(100)]
        public int $per_page = 20,
    ) {}
}
