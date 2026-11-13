<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\JobStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\CleaningObject;
use App\Models\ScheduledJob;
use App\Models\User;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

final class ScheduledJobPolicy
{
    public function __construct(private readonly TenantPermissionSnapshot $snapshot) {}

    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ViewSchedule->value);
    }

    public function view(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::ViewSchedule->value) && $job->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CreateSchedule->value);
    }

    public function update(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::EditSchedule->value) && $job->isEditable() && $job->isVisibleTo($user);
    }

    public function assign(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::AssignCleaners->value) && $job->canBeAssigned() && $job->isVisibleTo($user);
    }

    public function cancel(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::EditSchedule->value) && $job->canBeCancelled() && $job->isVisibleTo($user);
    }

    public function start(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::EditSchedule->value) && $job->canBeStarted() && $job->isVisibleTo($user);
    }

    public function complete(User $user, ScheduledJob $job): bool
    {
        return ($this->isEditor($user, $job) && $job->canBeCompleted())
            || ($this->isAssignee($user, $job) && $job->isCompletableByAssignee(Carbon::today(config()->string('scheduling.timezone'))));
    }

    public function markDone(User $user, ScheduledJob $job): bool
    {
        return $this->complete($user, $job)
            || ($job->status === JobStatusEnum::Completed && ($this->isEditor($user, $job) || $this->isAssignee($user, $job)));
    }

    public function viewOwnAcrossCompanies(User $user): bool
    {
        return array_any($this->snapshot->forRequest($user), static fn (TenantVisibility $visibility): bool => $visibility->schedule);
    }

    public function recordAnywhere(User $user): bool
    {
        return array_any(
            $this->snapshot->forRequest($user),
            static fn (TenantVisibility $visibility): bool => $visibility->membershipId !== null && in_array(PermissionEnum::RecordOwnWork, $visibility->permissions, true),
        );
    }

    public function recordWork(User $user, CleaningObject $object): bool
    {
        $membershipId = $user->activeMembershipId();

        return $user->can(PermissionEnum::RecordOwnWork->value)
            && $object->is_active
            && ! $object->trashed()
            && $membershipId !== null
            && CleaningObject::query()->reachableByMemberships([$membershipId])->whereKey($object->id)->exists();
    }

    public function createWorkRecord(User $user, CleaningObject $object): bool
    {
        return $user->can(PermissionEnum::CreateSchedule->value)
            && $user->can(PermissionEnum::AssignCleaners->value)
            && $object->isVisibleTo($user);
    }

    public function updateWorkRecord(User $user, ScheduledJob $job): bool
    {
        return $this->isWorkRecordEditor($user, $job);
    }

    public function deleteWorkRecord(User $user, ScheduledJob $job): bool
    {
        return $this->isWorkRecordEditor($user, $job);
    }

    public function viewWorkRecordPhoto(User $user, ScheduledJob $job): bool
    {
        if ($this->view($user, $job)) {
            return true;
        }

        $object = $job->cleaningObject;

        return $object !== null
            && $job->isWorkRecord()
            && Gate::forUser($user)->allows('viewVisitRecords', CleaningObject::class)
            && Gate::forUser($user)->allows('view', $object);
    }

    public function addPhoto(User $user, ScheduledJob $job): bool
    {
        return $this->isAssignee($user, $job)
            && $job->isWorkRecord()
            && $job->created_at->gte(now()->subDays(config()->integer('scheduling.work_records.photos.upload_window_days')));
    }

    public function unapprove(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::EditSchedule->value) && $job->canBeUnapproved() && $job->isVisibleTo($user);
    }

    private function isWorkRecordEditor(User $user, ScheduledJob $job): bool
    {
        return $this->isEditor($user, $job) && $job->isWorkRecord() && ! $job->isInvoiced();
    }

    private function isEditor(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::EditSchedule->value) && $job->isVisibleTo($user);
    }

    private function isAssignee(User $user, ScheduledJob $job): bool
    {
        return $user->can(PermissionEnum::RecordOwnWork->value) && $job->isAssignedTo($user);
    }
}
