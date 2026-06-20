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
        public string $id,
        public string $name,
        public string $body,
        public ContractCategoryEnum $category,
        public bool $is_active,
        public string $created_at,
    ) {}

    public static function fromModel(ContractTemplate $template): self
    {
        return new self(
            id: $template->id,
            name: $template->name,
            body: $template->body,
            category: $template->category,
            is_active: $template->is_active,
            created_at: $template->created_at->toDateTimeString(),
        );
    }
}
