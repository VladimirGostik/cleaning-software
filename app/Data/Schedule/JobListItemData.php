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
        public readonly string $id,
        public readonly string $scheduled_date,
        public readonly ?string $start_time,
        public readonly ?string $end_time,
        public readonly string $cleaning_object_id,
        public readonly string $object_name,
        public readonly string $client_name,
        public readonly ?string $assigned_membership_id,
        public readonly ?string $assignee_display_name,
        public readonly JobTypeEnum $type,
        public readonly JobStatusEnum $status,
        public readonly bool $is_invoiced,
    ) {}

    /** Expects eager `cleaningObject.client`, `assignedMembership.user`. */
    public static function fromModel(ScheduledJob $job): self
    {
        $object = $job->cleaningObject;
        $client = $object !== null ? $object->client : null;

        return new self(
            id: $job->id,
            scheduled_date: $job->scheduled_date->toDateString(),
            start_time: $job->start_time,
            end_time: $job->end_time,
            cleaning_object_id: $job->cleaning_object_id,
            object_name: $object !== null ? $object->name : '',
            client_name: $client !== null ? $client->name : '',
            assigned_membership_id: $job->assigned_membership_id,
            assignee_display_name: $job->assignedMembership?->display_name,
            type: $job->type,
            status: $job->status,
            is_invoiced: $job->invoice_id !== null,
        );
    }
}
