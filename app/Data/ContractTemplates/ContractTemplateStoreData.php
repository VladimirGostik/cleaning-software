<?php

declare(strict_types=1);

namespace App\Data\ContractTemplates;

use App\Enums\ContractCategoryEnum;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractTemplateStoreData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public string $name,
        #[Required]
        public ContractCategoryEnum $category,
        #[Required]
        public string $body,
        public bool $is_active = true,
    ) {}
}
