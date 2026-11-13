<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class WorkRecordObjectData extends Data
{
    /** @param WorkRecordItemOptionData[] $items */
    public function __construct(
        public readonly string $tenant_id,
        public readonly string $object_id,
        public readonly string $name,
        public readonly ?string $street,
        public readonly ?string $city,
        #[DataCollectionOf(WorkRecordItemOptionData::class)]
        public readonly array $items,
    ) {}
}
