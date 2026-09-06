<?php

declare(strict_types=1);

namespace App\Data\ContractTemplates;

use App\Enums\ContractCategoryEnum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractTemplateUpsertData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        #[Required]
        public readonly ContractCategoryEnum $category,
        #[Required, Max(50000)]
        public readonly string $body,
        public readonly bool $is_active = true,
    ) {}
}
