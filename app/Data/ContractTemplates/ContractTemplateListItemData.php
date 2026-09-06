<?php

declare(strict_types=1);

namespace App\Data\ContractTemplates;

use App\Enums\ContractCategoryEnum;
use App\Models\ContractTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractTemplateListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ContractCategoryEnum $category,
        public readonly bool $is_active,
        public readonly string $updated_at,
    ) {}

    public static function fromModel(ContractTemplate $template): self
    {
        return new self(
            id: $template->id,
            name: $template->name,
            category: $template->category,
            is_active: $template->is_active,
            updated_at: $template->updated_at->toIso8601String(),
        );
    }
}
