<?php

declare(strict_types=1);

namespace App\Data\DocumentTemplates;

use App\Data\Documents\DocumentLetterheadData;
use App\Enums\DocumentTemplateScopeEnum;
use App\Enums\DocumentTemplateStateEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Documents\DocumentFrame;
use App\Services\Documents\DocumentTemplateCatalogue;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DocumentTemplateEditData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly DocumentTypeEnum $type,
        public readonly ?EmploymentContractTypeEnum $employment_type,
        public readonly DocumentTemplateScopeEnum $scope,
        public readonly ?DocumentTemplateStateEnum $state,
        public readonly bool $is_active,
        public readonly string $body,
        public readonly ?string $base_body,
        public readonly ?string $previous_base_body,
        public readonly bool $show_header,
        public readonly bool $show_footer,
        /** @var DocumentTokenData[] */
        #[DataCollectionOf(DocumentTokenData::class)]
        public readonly array $tokens,
        public readonly DocumentLetterheadData $letterhead,
        /** @var string[] */
        public readonly array $footer,
        /** @var string[] */
        public readonly array $quote_footer,
        public readonly DocumentTemplateCanData $can,
    ) {}

    public static function fromModel(DocumentTemplate $template, Tenant $tenant, User $user, DocumentTemplateCatalogue $catalogue): self
    {
        $template->loadMissing('parent');
        $state = $template->state();

        return new self(
            id: $template->id,
            name: $template->name,
            type: $template->type,
            employment_type: $template->employment_type,
            scope: $template->templateScope(),
            state: $state,
            is_active: $template->is_active,
            body: $template->body,
            base_body: $template->parent?->body,
            previous_base_body: $state === DocumentTemplateStateEnum::BaseChanged ? $template->base_body : null,
            show_header: $template->show_header,
            show_footer: $template->show_footer,
            tokens: $catalogue->forType($template->type),
            letterhead: DocumentFrame::letterhead($tenant),
            footer: DocumentFrame::footer($tenant),
            quote_footer: DocumentFrame::quoteFooter($tenant),
            can: new DocumentTemplateCanData(
                update: $user->can('update', $template),
                delete: $user->can('delete', $template),
                customize: $user->can('customize', $template),
                reset: $user->can('reset', $template),
                acknowledge: $user->can('acknowledge', $template),
            ),
        );
    }
}
