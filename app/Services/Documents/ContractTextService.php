<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Contracts\ContractGenerateData;
use App\Data\DocumentTemplates\DocumentTokenData;
use App\Enums\ContractableTypeEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

final readonly class ContractTextService
{
    public function __construct(
        private DocumentTemplateRenderer $renderer,
        private DocumentContextBuilder $contexts,
        private DocumentTemplateCatalogue $catalogue,
    ) {}

    public function canReadText(Contract $contract, User $actor): bool
    {
        return $contract->contractable_type !== ContractableTypeEnum::TenantMembership->value
            || $actor->can(PermissionEnum::ViewEmployeePersonalData->value);
    }

    public function displayBody(Contract $contract): ?string
    {
        $contract->loadMissing('document');
        $body = $contract->document?->body;

        if ($body === null || ! str_contains($body, '{{block.')) {
            return $body;
        }

        $context = $this->contexts->forContract(
            $this->subject($contract),
            Tenant::withoutGlobalScopes()->findOrFail($contract->tenant_id),
            $this->terms($contract),
            $contract->quote,
        );

        return $this->renderer->render($body, new DocumentRenderContext(blocks: $context->blocks));
    }

    public function freezeBlocks(Contract $contract): void
    {
        $contract->loadMissing('document');
        $document = $contract->document;
        $expanded = $this->displayBody($contract);

        if ($document === null || $expanded === null || $expanded === $document->body) {
            return;
        }

        $document->update(['body' => $expanded]);
    }

    /** @return list<string> */
    public function missingTokens(Contract $contract): array
    {
        $contract->loadMissing('document');
        $body = $contract->document?->body;

        if ($body === null) {
            return [];
        }

        return array_values(array_diff(
            $this->renderer->unresolvedTokens($body),
            ['block.parties', 'block.signatures'],
        ));
    }

    /** @return list<DocumentTokenData> */
    public function tokens(Contract $contract): array
    {
        return $this->catalogue->forType(DocumentTypeEnum::fromCategory($contract->category));
    }

    public function terms(Contract $contract): ContractGenerateData
    {
        $contract->loadMissing(['employmentContract', 'document']);
        $employment = $contract->employmentContract;

        return ContractGenerateData::from([
            'category' => $contract->category->value,
            'term_type' => $contract->term_type->value,
            'contractable_type' => $contract->contractable_type,
            'contractable_id' => $contract->contractable_id,
            'valid_from' => $contract->valid_from->toDateString(),
            'end_date' => $contract->end_date?->toDateString(),
            'title' => $contract->title,
            'number' => $contract->number,
            'quote_id' => $contract->quote_id,
            'document_template_id' => $contract->document_template_id,
            'body' => null,
            'employment' => $employment === null ? null : [
                'employment_type' => $employment->employment_type->value,
                'position' => $employment->position,
                'hourly_rate' => $employment->hourly_rate !== null ? (float) $employment->hourly_rate : null,
                'monthly_salary' => $employment->monthly_salary !== null ? (float) $employment->monthly_salary : null,
                'weekly_hours' => $employment->weekly_hours !== null ? (float) $employment->weekly_hours : null,
                'probation_end_date' => $employment->probation_end_date?->toDateString(),
            ],
            'show_header' => $contract->document->show_header ?? true,
            'show_footer' => $contract->document->show_footer ?? true,
        ]);
    }

    private function subject(Contract $contract): CleaningObject|Client|TenantMembership
    {
        $contract->loadMissing('contractable');
        $subject = $contract->contractable;

        if ($subject instanceof TenantMembership) {
            $subject->loadMissing('user');
        }

        assert($subject instanceof CleaningObject || $subject instanceof Client || $subject instanceof TenantMembership);

        return $subject;
    }
}
