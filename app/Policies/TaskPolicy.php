<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Media;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;

/** Global tasks have no Spatie team, so they are creator-only. */
final class TaskPolicy
{
    public function __construct(private readonly TenantPermissionSnapshot $snapshot) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewTasks->value);
    }

    public function viewAnyAcrossCompanies(User $user): bool
    {
        return array_any($this->snapshot->forRequest($user), fn (TenantVisibility $visibility): bool => $visibility->tasks);
    }

    public function view(User $user, Task $task): bool
    {
        return $task->isVisibleTo($user);
    }

    /** `$tenantId` null asks "may this user create tasks somewhere" (also the global-task rule). */
    public function create(User $user, ?string $tenantId = null): bool
    {
        return $this->holds($user, PermissionEnum::CreateTasks, $tenantId);
    }

    /** Assigning at creation time, possibly in a tenant other than the bound one. */
    public function assignIn(User $user, string $tenantId): bool
    {
        return $this->holds($user, PermissionEnum::EditTasks, $tenantId);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->mayChange($user, $task, PermissionEnum::EditTasks);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->mayChange($user, $task, PermissionEnum::DeleteTasks);
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->mayChange($user, $task, PermissionEnum::EditTasks);
    }

    public function deleteAttachment(User $user, Task $task, Media $attachment): bool
    {
        return $this->mayDeleteAttachment($user, $task, $attachment, $task->isVisibleTo($user));
    }

    public function mayDeleteAttachment(User $user, Task $task, Media $attachment, bool $taskVisible): bool
    {
        if ($task->isGlobal()) {
            return $task->created_by_user_id === $user->id;
        }

        return $taskVisible
            && ($attachment->getCustomProperty('uploaded_by_user_id') === $user->id || $user->can(PermissionEnum::DeleteTasks->value));
    }

    public function comment(User $user, Task $task): bool
    {
        return $task->isVisibleTo($user);
    }

    public function deleteComment(User $user, Task $task, TaskComment $comment): bool
    {
        return $this->mayDeleteComment($user, $task, $comment, $task->isVisibleTo($user));
    }

    public function mayDeleteComment(User $user, Task $task, TaskComment $comment, bool $taskVisible): bool
    {
        if ($task->isGlobal()) {
            return $task->created_by_user_id === $user->id;
        }

        return $taskVisible && ($comment->user_id === $user->id || $user->can(PermissionEnum::DeleteTasks->value));
    }

    /** `$companyId` is the company a personal task runs in; a firm task always runs in its own. */
    public function startAiRun(User $user, Task $task, ?string $companyId = null): bool
    {
        if (! $task->isGlobal()) {
            return $task->isVisibleTo($user) && $user->can(PermissionEnum::RunTasksWithAi->value);
        }

        return $task->created_by_user_id === $user->id
            && $companyId !== null
            && $user->hasActiveMembership($companyId)
            && $this->holds($user, PermissionEnum::RunTasksWithAi, $companyId);
    }

    public function viewAiOutput(User $user, Task $task, string $startedByUserId): bool
    {
        return $task->isVisibleTo($user)
            && self::aiOutputAudienceIncludes($task->isGlobal(), $task->created_by_user_id === $user->id, $user->can(PermissionEnum::ViewAllTasks->value), $startedByUserId === $user->id);
    }

    public function seesAllAiOutput(User $user, Task $task): bool
    {
        return $task->isVisibleTo($user)
            && self::aiOutputAudienceIncludes($task->isGlobal(), $task->created_by_user_id === $user->id, $user->can(PermissionEnum::ViewAllTasks->value), false);
    }

    /** For callers that already know the task is visible and hold the per-company breadth permission, such as list pages across companies. */
    public static function aiOutputAudienceIncludes(bool $personalTask, bool $isCreator, bool $holdsViewAll, bool $isStarter): bool
    {
        return $personalTask ? $isCreator : ($holdsViewAll || $isStarter);
    }

    public static function changeAllowed(bool $personalTask, bool $isCreator, bool $holdsPermission): bool
    {
        return $personalTask ? $isCreator : $holdsPermission;
    }

    private function mayChange(User $user, Task $task, PermissionEnum $permission): bool
    {
        return self::changeAllowed(
            $task->isGlobal(),
            $task->created_by_user_id === $user->id,
            $task->isVisibleTo($user) && $user->can($permission->value),
        );
    }

    private function holds(User $user, PermissionEnum $permission, ?string $tenantId): bool
    {
        if (($tenantId === null || $tenantId === $this->boundTenantId()) && $user->can($permission->value)) {
            return true;
        }

        return array_any(
            $this->snapshot->forRequest($user),
            fn (TenantVisibility $visibility): bool => ($tenantId === null || $visibility->tenant->id === $tenantId)
                && in_array($permission, $visibility->permissions, true),
        );
    }

    private function boundTenantId(): ?string
    {
        /** @var string|null */
        return app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
    }
}
