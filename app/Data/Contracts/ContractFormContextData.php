<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Data\ContractTemplates\ContractTemplateOptionData;
use App\Data\Objects\ObjectOptionData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractFormContextData extends Data
{
    public function __construct(
        /** @var ObjectOptionData[] */
        #[DataCollectionOf(ObjectOptionData::class)]
        public readonly array $objects,
        /** @var MembershipOptionData[] */
        #[DataCollectionOf(MembershipOptionData::class)]
        public readonly array $memberships,
        /** @var ContractTemplateOptionData[] */
        #[DataCollectionOf(ContractTemplateOptionData::class)]
        public readonly array $templates,
        public readonly PlaceholderCatalogData $tokens,
    ) {}
}
