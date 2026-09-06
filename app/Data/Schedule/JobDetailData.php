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
    /** @param array<string, bool> $can */
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
        public readonly ?string $client_id,
        public readonly ?string $work_breakdown_id,
        public readonly ?string $work_breakdown_task_id,
        public readonly ?string $task_name,
        public readonly ?string $contract_id,
        public readonly ?string $contract_title,
        public readonly ?string $invoice_id,
        public readonly ?string $note,
        public readonly ?string $completed_at,
        public readonly ?string $cancelled_at,
        public readonly bool $is_editable,
        public readonly bool $can_be_assigned,
        public readonly bool $can_be_cancelled,
        public readonly array $can,
    ) {}

    /**
     * Expects eager `cleaningObject.client`, `assignedMembership.user`, `workBreakdownTask`, `contract:id,title`.
     *
     * @param  array<string, bool>  $can
     */
    public static function fromModel(ScheduledJob $job, array $can = []): self
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
            client_id: $object?->client_id,
            work_breakdown_id: $job->work_breakdown_id,
            work_breakdown_task_id: $job->work_breakdown_task_id,
            task_name: $job->workBreakdownTask?->name,
            contract_id: $job->contract_id,
            contract_title: $job->contract?->title,
            invoice_id: $job->invoice_id,
            note: $job->note,
            completed_at: $job->completed_at?->toIso8601String(),
            cancelled_at: $job->cancelled_at?->toIso8601String(),
            is_editable: $job->isEditable(),
            can_be_assigned: $job->canBeAssigned(),
            can_be_cancelled: $job->canBeCancelled(),
            can: $can,
        );
    }
}
