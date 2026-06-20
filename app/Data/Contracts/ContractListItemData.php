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
final class ContractListItemData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $reference_number,
        public ContractCategoryEnum $category,
        public ContractStatusEnum $status,
        public ContractTermTypeEnum $term_type,
        public string $valid_from,
        public ?string $end_date,
        public string $contractable_type,
        public string $contractable_display_name,
        public ?string $signed_at,
        public ?string $terminated_at,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        $contractable = $contract->contractable;

        $displayName = match (true) {
            $contractable instanceof CleaningObject => $contractable->name ?? '—',
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
            valid_from: $contract->valid_from->toDateString(),
            end_date: $contract->end_date?->toDateString(),
            contractable_type: $contract->contractable_type,
            contractable_display_name: $displayName,
            signed_at: $contract->signed_at?->toDateTimeString(),
            terminated_at: $contract->terminated_at?->toDateTimeString(),
        );
    }
}
