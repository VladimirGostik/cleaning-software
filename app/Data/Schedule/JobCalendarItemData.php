<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\ScheduledJob;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobCalendarItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $scheduled_date,
        public readonly ?string $start_time,
        public readonly ?string $end_time,
        public readonly string $object_name,
        public readonly ?string $assignee_display_name,
        public readonly JobTypeEnum $type,
        public readonly JobStatusEnum $status,
    ) {}

    public static function fromModel(ScheduledJob $job): self
    {
        $object = $job->cleaningObject;

        return new self(
            id: $job->id,
            scheduled_date: $job->scheduled_date->toDateString(),
            start_time: $job->start_time,
            end_time: $job->end_time,
            object_name: $object !== null ? $object->name : '',
            assignee_display_name: $job->assignedMembership?->display_name,
            type: $job->type,
            status: $job->status,
        );
    }
}
