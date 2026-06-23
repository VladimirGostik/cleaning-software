<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ChecksFeatures;
use App\Data\Employees\EmployeeIndexFilterData;
use App\Data\Employees\EmployeeUpsertData;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractStatusEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\FeatureEnum;
use App\Enums\InvitationStatusEnum;
use App\Enums\SubscriptionPlanEnum;
use App\Models\Contract;
use App\Models\EmploymentContract;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMembership;
use App\Models\User;
use App\Notifications\InvitationCreated;
use Illuminate\Database\DatabaseManager;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class EmployeeService
{
    public function __construct(
        private DatabaseManager $db,
        private ChecksFeatures $features,
        private PermissionRegistrar $permissionRegistrar,
        private JobService $jobService,
    ) {}

    /**
     * @return LengthAwarePaginator<TenantMembership>
     */
    public function paginate(EmployeeIndexFilterData $filter): LengthAwarePaginator
    {
        $tenantId = app('current_tenant_id');

        return QueryBuilder::for(TenantMembership::class)
            ->where('tenant_memberships.tenant_id', $tenantId)
            ->allowedFilters(
                AllowedFilter::callback('search', function ($query, string $value): void {
                    $op = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                    $query->where(function ($q) use ($value, $op): void {
                        $q->where('tenant_memberships.first_name', $op, '%' . $value . '%')
                            ->orWhere('tenant_memberships.last_name', $op, '%' . $value . '%')
                            ->orWhereHas('user', fn ($u) => $u->where('email', $op, '%' . $value . '%')
                                ->orWhere('name', $op, '%' . $value . '%'));
                    });
                }),
                AllowedFilter::callback('role', function ($query, string $value) use ($tenantId): void {
                    $query->whereHas('user.roles', fn ($q) => $q->where('name', $value)
                        ->where('tenant_id', $tenantId));
                }),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts(
                AllowedSort::field('joined_at'),
            )
            ->defaultSort('-joined_at')
            ->with(['user:id,name,email', 'user.roles'])
            ->paginate($filter->per_page)
            ->appends(request()->query());
    }

    public function create(EmployeeUpsertData $data): TenantMembership
    {
        return $this->db->transaction(function () use ($data): TenantMembership {
            $tenantId = app('current_tenant_id');

            /** @var Tenant $tenant */
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);

            $this->permissionRegistrar->setPermissionsTeamId($tenantId);

            // Quota check — null quota = unlimited.
            $quota = $this->features->getQuota($tenant, FeatureEnum::MultiUser);

            if ($quota !== null) {
                $activeCount = TenantMembership::where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->count();

                if ($activeCount >= $quota) {
                    throw ValidationException::withMessages([
                        'email' => [__('app.employees.quota_reached')],
                    ]);
                }
            }

            // Role escalation guard — actor may only assign roles whose full permission
            // set is a subset of their own permissions. Vlastník holds all → unaffected.
            /** @var Role $role */
            $role = Role::where('name', $data->role_name)
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            /** @var array<int, string> $actorPerms */
            $actorPerms = Auth::user()->getAllPermissions()->pluck('name')->all();
            $rolePerms = $role->permissions->pluck('name')->all();

            if (array_diff($rolePerms, $actorPerms) !== []) {
                throw ValidationException::withMessages([
                    'role_name' => [__('app.employees.role_not_assignable')],
                ]);
            }

            // Resolve or create the user.
            $isNewUser = false;
            $user = User::where('email', $data->email)->first();

            if ($user === null) {
                $isNewUser = true;
                $displayName = trim(($data->first_name ?? '') . ' ' . ($data->last_name ?? ''));
                $user = User::create([
                    'name' => $displayName !== '' ? $displayName : $data->email,
                    'email' => $data->email,
                    'password' => Hash::make(Str::random(32)),
                    'is_active' => true,
                    'subscription_plan' => SubscriptionPlanEnum::Free->value,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            // Guard against re-adding an already-active member.
            $existingMembership = TenantMembership::where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($existingMembership !== null && $existingMembership->is_active) {
                throw ValidationException::withMessages([
                    'email' => [__('app.employees.already_member')],
                ]);
            }

            if ($existingMembership !== null) {
                // Reactivate a deactivated membership.
                $existingMembership->update([
                    'is_active' => true,
                    'first_name' => $data->first_name,
                    'last_name' => $data->last_name,
                    'phone' => $data->phone,
                ]);
                $membership = $existingMembership;
            } else {
                $membership = TenantMembership::create([
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                    'joined_at' => now(),
                    'first_name' => $data->first_name,
                    'last_name' => $data->last_name,
                    'phone' => $data->phone,
                ]);
            }

            // Assign role within the tenant team scope.
            $user->syncRoles([$role]);

            // Sync direct permission overrides intersected with actor's own permissions
            // so no actor can grant a permission they don't hold themselves.
            // Empty array = no overrides, not a wipe (create-time semantics).
            if (! empty($data->permissions)) {
                $granted = array_values(array_intersect($data->permissions, $actorPerms));
                $user->syncPermissions($granted);
            }

            // Optionally create a draft employment contract linked to this membership.
            if ($data->employment !== null) {
                $displayName = $membership->display_name;
                $contract = Contract::create([
                    'tenant_id' => $tenantId,
                    'contractable_type' => 'tenant_membership',
                    'contractable_id' => $membership->id,
                    'category' => ContractCategoryEnum::Employment->value,
                    'status' => ContractStatusEnum::Draft->value,
                    'term_type' => ContractTermTypeEnum::Indefinite->value,
                    'title' => $displayName . ' — pracovná zmluva',
                    'body' => '',
                    'valid_from' => now()->toDateString(),
                ]);

                EmploymentContract::create([
                    'contract_id' => $contract->id,
                    'employment_type' => $data->employment->employment_type->value,
                    'position' => $data->employment->position,
                    'hourly_rate' => $data->employment->hourly_rate,
                    'monthly_salary' => $data->employment->monthly_salary,
                    'weekly_hours' => $data->employment->weekly_hours,
                    'probation_end_date' => $data->employment->probation_end_date,
                ]);
            }

            // Send invitation to newly-created users so they can set their password.
            if ($isNewUser) {
                /** @var User|null $inviter */
                $inviter = Auth::user();

                $invitation = TenantInvitation::firstOrCreate(
                    ['tenant_id' => $tenantId, 'email' => $data->email],
                    [
                        'invited_by_user_id' => $inviter?->id,
                        'role_name' => $data->role_name,
                        'token' => Str::random(64),
                        'status' => InvitationStatusEnum::Pending->value,
                        'expires_at' => now()->addDays(7),
                    ],
                );

                Notification::route('mail', $data->email)
                    ->notify(new InvitationCreated($invitation->token, $tenant->name, $data->role_name));
            }

            return $membership;
        });
    }

    public function update(TenantMembership $membership, EmployeeUpsertData $data): TenantMembership
    {
        return $this->db->transaction(function () use ($membership, $data): TenantMembership {
            $tenantId = app('current_tenant_id');

            $this->permissionRegistrar->setPermissionsTeamId($tenantId);

            $membership->update([
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'phone' => $data->phone,
            ]);

            if ($membership->user !== null) {
                /** @var Role $role */
                $role = Role::where('name', $data->role_name)
                    ->where('tenant_id', $tenantId)
                    ->firstOrFail();

                // Role escalation guard — actor may only assign roles whose full permission
                // set is a subset of their own permissions.
                /** @var array<int, string> $actorPerms */
                $actorPerms = Auth::user()->getAllPermissions()->pluck('name')->all();
                $rolePerms = $role->permissions->pluck('name')->all();

                if (array_diff($rolePerms, $actorPerms) !== []) {
                    throw ValidationException::withMessages([
                        'role_name' => [__('app.employees.role_not_assignable')],
                    ]);
                }

                $membership->user->syncRoles([$role]);

                // Always sync permissions on update (empty array = wipe direct overrides).
                // Intersect with actor's own permissions to prevent escalation.
                $granted = array_values(array_intersect($data->permissions, $actorPerms));
                $membership->user->syncPermissions($granted);
            }

            return $membership->refresh();
        });
    }

    public function deactivate(TenantMembership $membership): void
    {
        $this->db->transaction(function () use ($membership): void {
            $membership->update(['is_active' => false]);

            // Phase-2 hook: unassign all future jobs assigned to this membership.
            $this->jobService->unassignFutureForMembership($membership);
        });
    }
}
