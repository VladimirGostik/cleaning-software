<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Data\Documents\DocumentLetterheadData;
use App\Data\DocumentTemplates\DocumentTokenData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Documents\ContractTextService;
use App\Services\Documents\DocumentFrame;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractDetailData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $number,
        public readonly ContractCategoryEnum $category,
        public readonly ContractStatusEnum $status,
        public readonly ContractTermTypeEnum $term_type,
        public readonly ?string $body,
        public readonly bool $show_header,
        public readonly bool $show_footer,
        public readonly DocumentLetterheadData $letterhead,
        /** @var string[] */
        public readonly array $footer,
        /** @var string[] */
        public readonly array $missing_tokens,
        /** @var DocumentTokenData[] */
        #[DataCollectionOf(DocumentTokenData::class)]
        public readonly array $tokens,
        public readonly string $valid_from,
        public readonly ?string $end_date,
        public readonly ?string $signed_at,
        public readonly ?string $terminated_at,
        public readonly ?string $termination_reason,
        public readonly ?string $notes,
        public readonly ContractableTypeEnum $contractable_type,
        public readonly string $contractable_id,
        public readonly string $contractable_label,
        public readonly ?string $document_template_id,
        public readonly ?string $document_template_name,
        public readonly ?string $quote_id,
        public readonly ?string $quote_number,
        public readonly ?EmploymentContractData $employment,
        public readonly bool $is_editable,
        public readonly bool $can_be_signed,
        public readonly bool $can_be_terminated,
    ) {}

    public static function fromModel(Contract $contract, ContractTextService $text, User $actor): self
    {
        $contract->loadMissing(['employmentContract', 'documentTemplate', 'quote', 'contractable', 'document']);

        if ($contract->contractable instanceof TenantMembership) {
            $contract->contractable->loadMissing('user');
        }

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($contract->tenant_id);
        $canRead = $text->canReadText($contract, $actor);
        $body = $canRead ? $text->displayBody($contract) : null;
        $missing = $body !== null ? $text->missingTokens($contract) : [];

        return new self(
            id: $contract->id,
            title: $contract->title,
            number: $contract->number,
            category: $contract->category,
            status: $contract->status,
            term_type: $contract->term_type,
            body: $body,
            show_header: $contract->document->show_header ?? true,
            show_footer: $contract->document->show_footer ?? true,
            letterhead: DocumentFrame::letterhead($tenant),
            footer: DocumentFrame::footer($tenant),
            missing_tokens: $missing,
            tokens: $canRead ? $text->tokens($contract) : [],
            valid_from: $contract->valid_from->toDateString(),
            end_date: $contract->end_date?->toDateString(),
            signed_at: $contract->signed_at?->toIso8601String(),
            terminated_at: $contract->terminated_at?->toIso8601String(),
            termination_reason: $contract->termination_reason,
            notes: $contract->notes,
            contractable_type: ContractableTypeEnum::from($contract->contractable_type),
            contractable_id: $contract->contractable_id,
            contractable_label: $contract->contractableLabel(),
            document_template_id: $contract->document_template_id,
            document_template_name: $contract->documentTemplate?->name,
            quote_id: $contract->quote_id,
            quote_number: $contract->quote?->number,
            employment: $contract->employmentContract !== null
                ? EmploymentContractData::fromModel($contract->employmentContract)
                : null,
            is_editable: $contract->isEditable() && $canRead,
            can_be_signed: $contract->canBeSigned() && $canRead && $missing === [],
            can_be_terminated: $contract->canBeTerminated(),
        );
    }
}
