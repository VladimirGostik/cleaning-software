<?php

declare(strict_types=1);

namespace App\Data\DocumentTemplates;

use App\Enums\DocumentTemplateScopeEnum;
use App\Enums\DocumentTemplateStateEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\DocumentTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DocumentTemplateListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly DocumentTypeEnum $type,
        public readonly ?EmploymentContractTypeEnum $employment_type,
        public readonly DocumentTemplateScopeEnum $scope,
        public readonly ?DocumentTemplateStateEnum $state,
        public readonly bool $is_active,
        public readonly string $updated_at,
    ) {}

    public static function fromModel(DocumentTemplate $template): self
    {
        return new self(
            id: $template->id,
            name: $template->name,
            type: $template->type,
            employment_type: $template->employment_type,
            scope: $template->templateScope(),
            state: $template->state(),
            is_active: $template->is_active,
            updated_at: $template->updated_at->toIso8601String(),
        );
    }
}
