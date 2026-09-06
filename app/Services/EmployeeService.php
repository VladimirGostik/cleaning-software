<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contracts\ContractUpsertData;
use App\Data\Employees\EmployeeListItemData;
use App\Data\Employees\EmployeeStoreData;
use App\Data\Employees\EmployeeUpdateData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\JobStatusEnum;
use App\Enums\SupportedLanguage;
use App\Models\ContractTemplate;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Spatie\QueryBuilder\QueryBuilder;

final readonly class EmployeeService
{
    public function __construct(
        private DatabaseManager $db,
        private PermissionRegistrar $registrar,
        private RoleAssignmentGuard $guard,
        private JobService $jobs,
        private ContractService $contracts,
        private RegistrationService $registration,
    ) {}

    /**
     * @return LengthAwarePaginator<int, EmployeeListItemData>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $tenantId = current_tenant_id();

        return QueryBuilder::for(TenantMembership::query()->where('tenant_memberships.tenant_id', $tenantId))
            ->allowedFilters(
                AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                    if (! is_scalar($value) || $value === '') {
                        return;
                    }

                    $like = '%'.Filters::escapeLikeValue((string) $value).'%';
                    $operator = config('database.default') === 'pgsql' ? 'ilike' : 'like';

                    $query->where(function (Builder $q) use ($like, $operator): void {
                        $q->where('first_name', $operator, $like)
                            ->orWhere('last_name', $operator, $like)
                            ->orWhereHas('user', fn (Builder $u) => $u->where('name', $operator, $like)->orWhere('email', $operator, $like));
                    });
                }),
                AllowedFilter::relationExact('role', 'user.roles', 'name'),
                AllowedFilter::dynamic('is_active')->boolean(),
                AllowedFilter::dynamic('joined_at')->date(),
            )
            ->allowedSorts('joined_at', 'last_name', 'is_active')
            ->defaultSort('-joined_at')
            ->with(['user:id,name,email', 'user.roles', 'employmentContracts.employmentContract'])
            ->withCount(['scheduledJobs as upcoming_jobs_count' => fn (Builder $q) => $q
                ->whereDate('scheduled_date', '>=', today())
                ->where('status', JobStatusEnum::Planned)])
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (TenantMembership $membership) => EmployeeListItemData::fromModel($membership));
    }

    public function create(EmployeeStoreData $data, User $actor): TenantMembership
    {
        $tenantId = current_tenant_id();
        $this->registrar->setPermissionsTeamId($tenantId);

        /** @var Role $role */
        $role = Role::inTenant($tenantId)->where('name', $data->role_name)->with('permissions')->firstOrFail();
        $this->guard->assertAssignable($actor, [$role]);
        $this->guard->assertPermissionsGrantable($actor, $data->permissions ?? []);

        return $this->db->transaction(function () use ($data, $role, $tenantId): TenantMembership {
            $user = User::where('email', $data->email)->first();
            $isNew = $user === null;

            if ($user === null) {
                $user = User::create([
                    'name' => trim(($data->first_name ?? '').' '.($data->last_name ?? '')) ?: $data->email,
                    'email' => $data->email,
                    'password' => null,
                    'locale' => SupportedLanguage::getDefault()->value,
                    'is_active' => true,
                ]);
            }

            $membership = TenantMembership::query()
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->first();

            if ($membership !== null && $membership->is_active) {
                throw ValidationException::withMessages(['email' => [__('app.already_member')]]);
            }

            if ($membership !== null) {
                $membership->forceFill([
                    'is_active' => true,
                    'first_name' => $data->first_name,
                    'last_name' => $data->last_name,
                    'phone' => $data->phone,
                    'position' => $data->position,
                ])->save();
            } else {
                $membership = TenantMembership::create([
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                    'joined_at' => now(),
                    'first_name' => $data->first_name,
                    'last_name' => $data->last_name,
                    'phone' => $data->phone,
                    'position' => $data->position,
                ]);
            }

            $membership->setRelation('user', $user);

            $user->syncRoles([$role]);
            $user->syncPermissions($data->permissions ?? []);

            if ($data->employment !== null) {
                $template = ContractTemplate::query()
                    ->active()
                    ->where('category', ContractCategoryEnum::Employment->value)
                    ->orderBy('name')
                    ->first();

                $this->contracts->create(ContractUpsertData::from([
                    'title' => $membership->display_name.' — '.__('app.employment_contract_default_title'),
                    'number' => null,
                    'category' => ContractCategoryEnum::Employment->value,
                    'term_type' => ContractTermTypeEnum::Indefinite->value,
                    'contractable_type' => ContractableTypeEnum::TenantMembership->value,
                    'contractable_id' => $membership->id,
                    'contract_template_id' => $template?->id,
                    'body' => $template->body ?? '',
                    'valid_from' => today()->toDateString(),
                    'end_date' => null,
                    'notes' => null,
                    'employment' => $data->employment,
                ]));
            }

            if ($isNew) {
                $tenant = Tenant::query()->findOrFail($tenantId);
                $this->registration->createInvitation($tenant, $user, $data->email, $data->role_name);
            }

            return $membership->load(['user:id,name,email', 'user.roles', 'employmentContracts.employmentContract']);
        });
    }

    public function update(TenantMembership $membership, EmployeeUpdateData $data, User $actor): TenantMembership
    {
        $tenantId = current_tenant_id();
        $this->registrar->setPermissionsTeamId($tenantId);

        /** @var Role $role */
        $role = Role::inTenant($tenantId)->where('name', $data->role_name)->with('permissions')->firstOrFail();
        $this->guard->assertAssignable($actor, [$role]);
        $this->guard->assertPermissionsGrantable($actor, $data->permissions ?? []);

        return $this->db->transaction(function () use ($membership, $data, $role): TenantMembership {
            $membership->update([
                'first_name' => $data->first_name,
                'last_name' => $data->last_name,
                'phone' => $data->phone,
                'position' => $data->position,
            ]);

            $membership->loadMissing('user');
            /** @var User $membershipUser */
            $membershipUser = $membership->user;
            $membershipUser->syncRoles([$role]);
            $membershipUser->syncPermissions($data->permissions ?? []);

            return $membership->load(['user:id,name,email', 'user.roles', 'employmentContracts.employmentContract']);
        });
    }

    public function deactivate(TenantMembership $membership): int
    {
        return $this->db->transaction(function () use ($membership): int {
            $membership->update(['is_active' => false]);

            return $this->jobs->unassignFutureForMembership($membership);
        });
    }

    public function reactivate(TenantMembership $membership): void
    {
        $this->db->transaction(function () use ($membership): void {
            $membership->update(['is_active' => true]);
        });
    }

    public function assignRole(TenantMembership $membership, string $roleName, User $actor): TenantMembership
    {
        $tenantId = current_tenant_id();
        $this->registrar->setPermissionsTeamId($tenantId);

        /** @var Role $role */
        $role = Role::inTenant($tenantId)->where('name', $roleName)->with('permissions')->firstOrFail();
        $this->guard->assertAssignable($actor, [$role]);

        return $this->db->transaction(function () use ($membership, $role): TenantMembership {
            $membership->loadMissing('user');
            /** @var User $membershipUser */
            $membershipUser = $membership->user;
            $membershipUser->syncRoles([$role]);

            return $membership->load(['user:id,name,email', 'user.roles', 'employmentContracts.employmentContract']);
        });
    }
}
