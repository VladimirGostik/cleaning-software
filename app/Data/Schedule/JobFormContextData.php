<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Data\Contracts\MembershipOptionData;
use App\Data\Objects\ObjectOptionData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobFormContextData extends Data
{
    /**
     * @param  ObjectOptionData[]  $objects
     * @param  MembershipOptionData[]  $memberships
     */
    public function __construct(
        #[DataCollectionOf(ObjectOptionData::class)]
        public readonly array $objects,
        #[DataCollectionOf(MembershipOptionData::class)]
        public readonly array $memberships,
    ) {}
}
