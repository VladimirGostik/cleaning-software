<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\ScheduledJob;
use App\Models\ScheduledJobItem;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MyJobData extends Data
{
    /** @param list<string> $items */
    public function __construct(
        public readonly string $id,
        public readonly string $tenant_id,
        public readonly string $scheduled_date,
        public readonly ?string $start_time,
        public readonly ?string $end_time,
        public readonly string $object_name,
        public readonly ?string $object_street,
        public readonly ?string $object_city,
        public readonly ?string $task_name,
        public readonly JobTypeEnum $type,
        public readonly JobStatusEnum $status,
        public readonly ?string $note,
        public readonly ?string $completed_at,
        public readonly bool $can_complete,
        public readonly array $items,
        public readonly int $photos_count,
    ) {}

    /** Expects eager `cleaningObject`, `workBreakdownTask`, `items` and the `photos_count` aggregate. */
    public static function fromModel(ScheduledJob $job, bool $canComplete): self
    {
        $object = $job->cleaningObject;

        return new self(
            id: $job->id,
            tenant_id: $job->tenant_id,
            scheduled_date: $job->scheduled_date->toDateString(),
            start_time: $job->start_time,
            end_time: $job->end_time,
            object_name: $object !== null ? $object->name : '',
            object_street: $object?->street,
            object_city: $object?->city,
            task_name: $job->workBreakdownTask?->name,
            type: $job->type,
            status: $job->status,
            note: $job->note,
            completed_at: $job->completed_at?->toIso8601ZuluString(),
            can_complete: $canComplete,
            items: array_values(array_map(static fn (ScheduledJobItem $item): string => $item->name, $job->items->all())),
            photos_count: (int) $job->photos_count,
        );
    }
}
