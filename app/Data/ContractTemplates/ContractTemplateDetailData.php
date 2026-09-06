<?php

declare(strict_types=1);

namespace App\Data\ContractTemplates;

use App\Enums\ContractCategoryEnum;
use App\Models\ContractTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractTemplateDetailData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ContractCategoryEnum $category,
        public readonly string $body,
        public readonly bool $is_active,
    ) {}

    public static function fromModel(ContractTemplate $template): self
    {
        return new self(
            id: $template->id,
            name: $template->name,
            category: $template->category,
            body: $template->body,
            is_active: $template->is_active,
        );
    }
}
