<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Enums\EmployeeTypeEnum;
use App\Models\Role;
use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $user_id,
        public readonly string $display_name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $position,
        public readonly ?string $role_name,
        public readonly ?EmployeeTypeEnum $employee_type,
        public readonly bool $has_access,
        public readonly bool $invitation_pending,
        public readonly int $upcoming_jobs_count,
        public readonly bool $is_active,
        public readonly string $joined_at,
    ) {}

    /**
     * Expects eager `user:id,name,email,password`, `user.roles`,
     * `withCount('scheduledJobs as upcoming_jobs_count' => ...)`.
     */
    public static function fromModel(TenantMembership $membership): self
    {
        $user = $membership->user;
        /** @var Role|null $role */
        $role = $user?->roles->first();

        return new self(
            id: $membership->id,
            user_id: $membership->user_id,
            display_name: $membership->display_name,
            email: $membership->display_email,
            phone: $membership->phone,
            position: $membership->position,
            role_name: $role?->name,
            employee_type: $membership->employee_type,
            has_access: $membership->hasAppAccess(),
            invitation_pending: $membership->isInvitationPending(),
            upcoming_jobs_count: $membership->upcoming_jobs_count ?? 0,
            is_active: $membership->is_active,
            joined_at: $membership->joined_at->toDateString(),
        );
    }
}
