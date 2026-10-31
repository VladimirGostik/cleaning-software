<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Task;
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

    private function mayChange(User $user, Task $task, PermissionEnum $permission): bool
    {
        if ($task->isGlobal()) {
            return $task->created_by_user_id === $user->id;
        }

        return $task->isVisibleTo($user) && $user->can($permission->value);
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
