<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Data\Contracts\EmploymentContractData;
use App\Enums\EmploymentContractTypeEnum;
use App\Models\Contract;
use App\Models\Role;
use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeDetailData extends Data
{
    /**
     * @param  list<string>  $permissions
     * @param  array<string, bool>  $can
     */
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
        public readonly string $user_name,
        public readonly ?string $first_name,
        public readonly ?string $last_name,
        public readonly array $permissions,
        public readonly ?string $employment_contract_id,
        public readonly ?EmploymentContractData $employment_contract,
        public readonly int $other_tenants_count,
        public readonly bool $is_owner,
        public readonly array $can,
    ) {}

    /**
     * Expects eager `user`, `user.roles`, `employmentContracts.employmentContract`,
     * `withCount(...)` as {@see EmployeeListItemData}.
     *
     * @param  array<string, bool>  $can
     */
    public static function fromModel(TenantMembership $membership, array $can = []): self
    {
        /** @var Contract|null $latestEmploymentContract */
        $latestEmploymentContract = $membership->employmentContracts->sortByDesc('created_at')->first();
        $employmentContract = $latestEmploymentContract?->employmentContract;

        $user = $membership->user;
        /** @var Role|null $role */
        $role = $user?->roles->first();
        /** @var list<string> $directPermissions */
        $directPermissions = $user?->getDirectPermissions()->pluck('name')->all() ?? [];
        $otherTenantsCount = $user?->memberships()->where('tenant_id', '!=', $membership->tenant_id)->count() ?? 0;
        $tenant = $membership->tenant;

        return new self(
            id: $membership->id,
            user_id: $membership->user_id,
            display_name: $membership->display_name,
            email: $user->email ?? '',
            phone: $membership->phone,
            position: $membership->position,
            role_name: $role?->name,
            employment_type: $employmentContract?->employment_type,
            upcoming_jobs_count: $membership->upcoming_jobs_count ?? 0,
            is_active: $membership->is_active,
            joined_at: $membership->joined_at->toDateString(),
            user_name: $user->name ?? '',
            first_name: $membership->first_name,
            last_name: $membership->last_name,
            permissions: $directPermissions,
            employment_contract_id: $latestEmploymentContract?->id,
            employment_contract: $employmentContract !== null ? EmploymentContractData::fromModel($employmentContract) : null,
            other_tenants_count: $otherTenantsCount,
            is_owner: $tenant !== null && $tenant->owner_id === $membership->user_id,
            can: $can,
        );
    }
}
