<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Enums\EmploymentContractTypeEnum;
use App\Models\Contract;
use App\Models\Role;
use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $user_id,
        public readonly string $display_name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $position,
        public readonly ?string $role_name,
        public readonly ?EmploymentContractTypeEnum $employment_type,
        public readonly int $upcoming_jobs_count,
        public readonly bool $is_active,
        public readonly string $joined_at,
    ) {}

    /**
     * Expects eager `user:id,name,email`, `user.roles`, `employmentContracts.employmentContract`,
     * `withCount('scheduledJobs as upcoming_jobs_count' => ...)`.
     */
    public static function fromModel(TenantMembership $membership): self
    {
        /** @var Contract|null $latestEmploymentContract */
        $latestEmploymentContract = $membership->employmentContracts->sortByDesc('created_at')->first();

        $user = $membership->user;
        /** @var Role|null $role */
        $role = $user?->roles->first();

        return new self(
            id: $membership->id,
            user_id: $membership->user_id,
            display_name: $membership->display_name,
            email: $user->email ?? '',
            phone: $membership->phone,
            position: $membership->position,
            role_name: $role?->name,
            employment_type: $latestEmploymentContract?->employmentContract?->employment_type,
            upcoming_jobs_count: $membership->upcoming_jobs_count ?? 0,
            is_active: $membership->is_active,
            joined_at: $membership->joined_at->toDateString(),
        );
    }
}
