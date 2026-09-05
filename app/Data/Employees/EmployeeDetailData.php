<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Data\Contracts\EmploymentContractData;
use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeDetailData extends Data
{
    /**
     * @param  list<string>  $permissions
     * @param  list<mixed>  $assigned_objects
     */
    public function __construct(
        public string $id,
        public string $user_id,
        public string $user_email,
        public string $user_name,
        public ?string $role_name,
        public array $permissions,
        public ?string $first_name,
        public ?string $last_name,
        public ?string $phone,
        public ?string $position,
        public string $display_name,
        public ?EmploymentContractData $employment_contract,
        public int $other_tenants_count,
        public array $assigned_objects,
        public bool $is_active,
        public string $joined_at,
    ) {}

    public static function fromModel(TenantMembership $membership): self
    {
        $latestContract = $membership->employmentContracts
            ->sortByDesc('created_at')
            ->first();

        return new self(
            id: $membership->id,
            user_id: $membership->user->id ?? '',
            user_email: $membership->user->email ?? '',
            user_name: $membership->user->name ?? '',
            role_name: $membership->user?->roles->first()?->name,
            permissions: $membership->user?->getDirectPermissions()->pluck('name')->all() ?? [],
            first_name: $membership->first_name,
            last_name: $membership->last_name,
            phone: $membership->phone,
            position: $membership->position,
            display_name: $membership->display_name,
            employment_contract: $latestContract?->employmentContract !== null
                ? EmploymentContractData::fromModel($latestContract->employmentContract)
                : null,
            other_tenants_count: $membership->user?->memberships()
                ->where('is_active', true)
                ->where('tenant_id', '!=', $membership->tenant_id)
                ->count() ?? 0,
            assigned_objects: [],
            is_active: (bool) $membership->is_active,
            joined_at: $membership->joined_at->toDateString(),
        );
    }
}
