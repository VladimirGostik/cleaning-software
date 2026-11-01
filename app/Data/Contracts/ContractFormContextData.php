<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Data\Clients\ClientOptionData;
use App\Data\Documents\DocumentLetterheadData;
use App\Data\DocumentTemplates\DocumentTemplateOptionData;
use App\Data\DocumentTemplates\DocumentTokenCatalogData;
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
        /** @var ClientOptionData[] */
        #[DataCollectionOf(ClientOptionData::class)]
        public readonly array $clients,
        /** @var MembershipOptionData[] */
        #[DataCollectionOf(MembershipOptionData::class)]
        public readonly array $memberships,
        /** @var DocumentTemplateOptionData[] */
        #[DataCollectionOf(DocumentTemplateOptionData::class)]
        public readonly array $templates,
        public readonly DocumentTokenCatalogData $tokens,
        public readonly DocumentLetterheadData $letterhead,
        /** @var string[] */
        public readonly array $footer,
    ) {}
}
