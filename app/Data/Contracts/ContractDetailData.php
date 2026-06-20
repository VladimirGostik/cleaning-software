<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\CleaningObject;
use App\Models\Contract;
use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractDetailData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $reference_number,
        public ContractCategoryEnum $category,
        public ContractStatusEnum $status,
        public ContractTermTypeEnum $term_type,
        public string $body,
        public string $valid_from,
        public ?string $end_date,
        public ?string $signed_at,
        public ?string $terminated_at,
        public ?string $termination_reason,
        public ?string $notes,
        public string $contractable_id,
        public string $contractable_type,
        public string $contractable_display_name,
        public ?string $contract_template_id,
        public ?string $contract_template_name,
        public ?EmploymentContractData $employment,
        public bool $is_editable,
        public bool $can_be_signed,
        public bool $can_be_terminated,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        $contract->loadMissing(['employmentContract', 'contractTemplate', 'contractable']);

        $contractable = $contract->contractable;

        if ($contractable instanceof TenantMembership) {
            $contractable->loadMissing('user');
        }

        $displayName = match (true) {
            $contractable instanceof CleaningObject => $contractable->name,
            $contractable instanceof TenantMembership => $contractable->user !== null
                ? $contractable->user->name . ' (' . $contractable->user->email . ')'
                : '—',
            default => '—',
        };

        return new self(
            id: $contract->id,
            title: $contract->title,
            reference_number: $contract->reference_number,
            category: $contract->category,
            status: $contract->status,
            term_type: $contract->term_type,
            body: $contract->body,
            valid_from: $contract->valid_from->toDateString(),
            end_date: $contract->end_date?->toDateString(),
            signed_at: $contract->signed_at?->toDateTimeString(),
            terminated_at: $contract->terminated_at?->toDateTimeString(),
            termination_reason: $contract->termination_reason,
            notes: $contract->notes,
            contractable_id: $contract->contractable_id,
            contractable_type: $contract->contractable_type,
            contractable_display_name: $displayName,
            contract_template_id: $contract->contract_template_id,
            contract_template_name: $contract->contractTemplate?->name,
            employment: $contract->employmentContract !== null
                ? EmploymentContractData::fromModel($contract->employmentContract)
                : null,
            is_editable: $contract->isEditable(),
            can_be_signed: $contract->canBeSigned(),
            can_be_terminated: $contract->canBeTerminated(),
        );
    }
}
