<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use App\Enums\ContractStatusEnum;
use App\Models\Contract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class QuoteContractLinkData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $number,
        public readonly ContractStatusEnum $status,
    ) {}

    public static function fromModel(Contract $contract): self
    {
        return new self(
            id: $contract->id,
            title: $contract->title,
            number: $contract->number,
            status: $contract->status,
        );
    }
}
