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
        public string $id,
        public string $name,
        public ContractCategoryEnum $category,
        public string $body,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(ContractTemplate $template): self
    {
        return new self(
            id: $template->id,
            name: $template->name,
            category: $template->category,
            body: $template->body,
            is_active: $template->is_active,
            created_at: $template->created_at->toDateTimeString(),
            updated_at: $template->updated_at->toDateTimeString(),
        );
    }
}
