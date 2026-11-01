<?php

declare(strict_types=1);

namespace App\Data\DocumentTemplates;

use App\Enums\DocumentTemplateScopeEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\DocumentTemplate;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DocumentTemplateOptionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly DocumentTypeEnum $type,
        public readonly ?EmploymentContractTypeEnum $employment_type,
        public readonly DocumentTemplateScopeEnum $scope,
    ) {}

    public static function fromModel(DocumentTemplate $template): self
    {
        return new self(
            id: $template->id,
            name: $template->name,
            type: $template->type,
            employment_type: $template->employment_type,
            scope: $template->templateScope(),
        );
    }
}
