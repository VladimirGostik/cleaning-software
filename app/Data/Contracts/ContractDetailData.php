<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\Contract;
use App\Models\TenantMembership;
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
        public readonly string $body,
        public readonly string $valid_from,
        public readonly ?string $end_date,
        public readonly ?string $signed_at,
        public readonly ?string $terminated_at,
        public readonly ?string $termination_reason,
        public readonly ?string $notes,
        public readonly ContractableTypeEnum $contractable_type,
        public readonly string $contractable_id,
        public readonly string $contractable_label,
        public readonly ?string $contract_template_id,
        public readonly ?string $contract_template_name,
        public readonly ?string $quote_id,
        public readonly ?string $quote_number,
        public readonly ?EmploymentContractData $employment,
        public readonly bool $is_editable,
        public readonly bool $can_be_signed,
        public readonly bool $can_be_terminated,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        $contract->loadMissing(['employmentContract', 'contractTemplate', 'quote', 'contractable']);

        if ($contract->contractable instanceof TenantMembership) {
            $contract->contractable->loadMissing('user');
        }

        return new self(
            id: $contract->id,
            title: $contract->title,
            number: $contract->number,
            category: $contract->category,
            status: $contract->status,
            term_type: $contract->term_type,
            body: $contract->body,
            valid_from: $contract->valid_from->toDateString(),
            end_date: $contract->end_date?->toDateString(),
            signed_at: $contract->signed_at?->toIso8601String(),
            terminated_at: $contract->terminated_at?->toIso8601String(),
            termination_reason: $contract->termination_reason,
            notes: $contract->notes,
            contractable_type: ContractableTypeEnum::from($contract->contractable_type),
            contractable_id: $contract->contractable_id,
            contractable_label: $contract->contractableLabel(),
            contract_template_id: $contract->contract_template_id,
            contract_template_name: $contract->contractTemplate?->name,
            quote_id: $contract->quote_id,
            quote_number: $contract->quote?->number,
            employment: $contract->employmentContract !== null
                ? EmploymentContractData::fromModel($contract->employmentContract)
                : null,
            is_editable: $contract->isEditable(),
            can_be_signed: $contract->canBeSigned(),
            can_be_terminated: $contract->canBeTerminated(),
        );
    }
}
