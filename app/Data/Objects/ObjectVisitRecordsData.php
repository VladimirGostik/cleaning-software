<?php

declare(strict_types=1);

namespace App\Data\Objects;

use App\Data\Contracts\MembershipOptionData;
use App\Data\Schedule\JobWorkRecordData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ObjectVisitRecordsData extends Data
{
    /**
     * @param  ObjectVisitSummaryLineData[]  $summary
     * @param  JobWorkRecordData[]  $records
     * @param  MembershipOptionData[]  $members
     */
    public function __construct(
        public readonly string $month,
        #[DataCollectionOf(ObjectVisitSummaryLineData::class)]
        public readonly array $summary,
        public readonly string $total,
        #[DataCollectionOf(JobWorkRecordData::class)]
        public readonly array $records,
        public readonly bool $can_add,
        #[DataCollectionOf(MembershipOptionData::class)]
        public readonly array $members,
    ) {}
}
