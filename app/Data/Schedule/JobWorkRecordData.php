<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobWorkRecordData extends Data
{
    /**
     * @param  JobWorkRecordItemData[]  $items
     * @param  JobWorkRecordPhotoData[]  $photos
     */
    public function __construct(
        public readonly string $job_id,
        public readonly string $performed_at,
        public readonly ?string $tenant_membership_id,
        public readonly ?string $recorded_by_name,
        public readonly ?string $note,
        #[DataCollectionOf(JobWorkRecordItemData::class)]
        public readonly array $items,
        #[DataCollectionOf(JobWorkRecordPhotoData::class)]
        public readonly array $photos,
        public readonly bool $can_update,
        public readonly bool $can_delete,
    ) {}
}
