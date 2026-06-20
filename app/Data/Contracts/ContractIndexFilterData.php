<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractIndexFilterData extends Data
{
    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?string $category = null,
        public ?string $term_type = null,
        public ?string $contractable_type = null,
        public int $per_page = 15,
    ) {}
}
