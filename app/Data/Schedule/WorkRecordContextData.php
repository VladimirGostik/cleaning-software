<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class WorkRecordContextData extends Data
{
    /** @param WorkRecordObjectData[] $objects */
    public function __construct(
        #[DataCollectionOf(WorkRecordObjectData::class)]
        public readonly array $objects,
        public readonly int $backdate_days,
        public readonly int $max_photos,
        public readonly int $max_photo_size_kb,
    ) {}
}
