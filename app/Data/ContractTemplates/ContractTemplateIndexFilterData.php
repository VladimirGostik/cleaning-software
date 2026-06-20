<?php

declare(strict_types=1);

namespace App\Data\ContractTemplates;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractTemplateIndexFilterData extends Data
{
    public function __construct(
        public ?string $search = null,
        public ?string $category = null,
        public ?bool $is_active = null,
        public int $per_page = 15,
    ) {}
}
