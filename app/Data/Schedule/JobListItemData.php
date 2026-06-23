<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\ScheduledJob;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobListItemData extends Data
{
    public function __construct(
        public string $id,
        public string $scheduled_date,
        public ?string $start_time,
        public ?string $end_time,
        public string $object_name,
        public string $client_name,
        public ?string $assignee_display_name,
        public JobTypeEnum $type,
        public JobStatusEnum $status,
        public bool $is_invoiced,
    ) {}

    public static function fromModel(ScheduledJob $job): self
    {
        return new self(
            id: $job->id,
            scheduled_date: $job->scheduled_date->toDateString(),
            start_time: $job->start_time,
            end_time: $job->end_time,
            object_name: $job->cleaningObject?->name ?? '',
            client_name: $job->cleaningObject?->client?->name ?? '',
            assignee_display_name: $job->assignedMembership?->display_name,
            type: $job->type,
            status: $job->status,
            is_invoiced: $job->invoice_id !== null,
        );
    }
}
