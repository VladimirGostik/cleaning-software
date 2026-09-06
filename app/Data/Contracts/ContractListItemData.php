<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Models\Contract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $number,
        public readonly ContractCategoryEnum $category,
        public readonly ContractStatusEnum $status,
        public readonly ContractTermTypeEnum $term_type,
        public readonly string $valid_from,
        public readonly ?string $end_date,
        public readonly ContractableTypeEnum $contractable_type,
        public readonly string $contractable_label,
        public readonly ?string $signed_at,
        public readonly bool $is_editable,
        public readonly bool $can_be_signed,
        public readonly bool $can_be_terminated,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        return new self(
            id: $contract->id,
            title: $contract->title,
            number: $contract->number,
            category: $contract->category,
            status: $contract->status,
            term_type: $contract->term_type,
            valid_from: $contract->valid_from->toDateString(),
            end_date: $contract->end_date?->toDateString(),
            contractable_type: ContractableTypeEnum::from($contract->contractable_type),
            contractable_label: $contract->contractableLabel(),
            signed_at: $contract->signed_at?->toIso8601String(),
            is_editable: $contract->isEditable(),
            can_be_signed: $contract->canBeSigned(),
            can_be_terminated: $contract->canBeTerminated(),
        );
    }
}
