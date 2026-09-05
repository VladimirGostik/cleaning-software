<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Enums\JobStatusEnum;
use App\Enums\JobTypeEnum;
use App\Models\ScheduledJob;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobDetailData extends Data
{
    public function __construct(
        public string $id,
        public string $cleaning_object_id,
        public string $object_name,
        public string $client_name,
        public ?string $client_id,
        public ?string $assigned_membership_id,
        public ?string $assignee_display_name,
        public ?string $work_breakdown_id,
        public ?string $work_breakdown_task_id,
        public ?string $contract_id,
        public ?string $invoice_id,
        public JobTypeEnum $type,
        public JobStatusEnum $status,
        public string $scheduled_date,
        public ?string $start_time,
        public ?string $end_time,
        public ?string $note,
        public bool $is_invoiced,
        public bool $is_editable,
        public bool $can_be_cancelled,
        /** @var array<string, bool> */
        public array $can,
    ) {}

    /**
     * @param  array<string, bool>  $can
     */
    public static function fromModel(ScheduledJob $job, array $can = []): self
    {
        return new self(
            id: $job->id,
            cleaning_object_id: $job->cleaning_object_id,
            object_name: $job->cleaningObject?->name ?? '',
            client_name: $job->cleaningObject?->client->name ?? '',
            client_id: $job->cleaningObject?->client?->id,
            assigned_membership_id: $job->assigned_membership_id,
            assignee_display_name: $job->assignedMembership?->display_name,
            work_breakdown_id: $job->work_breakdown_id,
            work_breakdown_task_id: $job->work_breakdown_task_id,
            contract_id: $job->contract_id,
            invoice_id: $job->invoice_id,
            type: $job->type,
            status: $job->status,
            scheduled_date: $job->scheduled_date->toDateString(),
            start_time: $job->start_time,
            end_time: $job->end_time,
            note: $job->note,
            is_invoiced: $job->invoice_id !== null,
            is_editable: $job->isEditable(),
            can_be_cancelled: $job->canBeCancelled(),
            can: $can,
        );
    }
}
