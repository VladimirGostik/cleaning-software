<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskPriorityEnum;
use App\Enums\TaskRunStatusEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Task;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $tenant_id,
        public readonly ?string $tenant_name,
        public readonly string $title,
        public readonly TaskStatusEnum $status,
        public readonly TaskPriorityEnum $priority,
        public readonly ?string $due_at,
        public readonly ?string $assignee_name,
        public readonly ?TaskSubjectData $subject,
        public readonly bool $is_global,
        public readonly string $created_at,
        public readonly int $comments_count,
        public readonly int $attachments_count,
        public readonly bool $can_update,
        public readonly bool $is_active,
        public readonly ?TaskRunStatusEnum $ai_run_status = null,
    ) {}

    /** Expects `tenant`, `assignedMembership.user` and `taskable` eager loaded; the counts come from `withCount` on list queries. */
    public static function fromModel(Task $task, bool $canUpdate, ?TaskRunStatusEnum $aiRunStatus = null): self
    {
        return new self(
            id: $task->id,
            tenant_id: $task->tenant_id,
            tenant_name: $task->tenant?->name,
            title: $task->title,
            status: $task->status,
            priority: $task->priority,
            due_at: $task->due_at?->toIso8601String(),
            assignee_name: $task->assignedMembership?->display_name,
            subject: TaskSubjectData::fromTask($task),
            is_global: $task->isGlobal(),
            created_at: $task->created_at->toIso8601String(),
            comments_count: $task->comments_count ?? 0,
            attachments_count: $task->attachments_count ?? 0,
            can_update: $canUpdate,
            is_active: $task->isActive(),
            ai_run_status: $aiRunStatus,
        );
    }
}
