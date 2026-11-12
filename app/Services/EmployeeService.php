<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Contracts\ContractUpsertData;
use App\Data\Employees\EmployeeAccessData;
use App\Data\Employees\EmployeeListItemData;
use App\Data\Employees\EmployeeStoreData;
use App\Data\Employees\EmployeeUpdateData;
use App\Enums\ContractableTypeEnum;
use App\Enums\ContractCategoryEnum;
use App\Enums\ContractTermTypeEnum;
use App\Enums\DocumentTypeEnum;
use App\Enums\EmployeeTypeEnum;
use App\Enums\EmploymentContractTypeEnum;
use App\Enums\JobStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\DocumentTemplate;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\Documents\DocumentTemplateCatalogue;
use App\Services\Documents\DocumentTemplateService;
use App\Utils\AllowedFilter;
use App\Utils\Filters;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
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
        private TaskService $tasks,
        private ContractService $contracts,
        private DocumentTemplateService $templates,
        private DocumentTemplateCatalogue $catalogue,
        private RegistrationService $registration,
        private MobileSessionService $sessions,
        private EmployeeAccessService $access,
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

                    $needle = (string) $value;

                    $query->where(function (Builder $q) use ($needle): void {
                        Filters::whereContains($q, 'first_name', $needle);
                        Filters::whereContains($q, 'last_name', $needle, 'or');
                        Filters::whereContains($q, 'contact_email', $needle, 'or');
                        $q->orWhereHas('user', function (Builder $u) use ($needle): void {
                            Filters::whereContains($u, 'name', $needle);
                            Filters::whereContains($u, 'email', $needle, 'or');
                        });
                    });
                }),
                AllowedFilter::relationExact('role', 'user.roles', 'name'),
                AllowedFilter::exact('employee_type'),
                AllowedFilter::callback('has_access', function (Builder $query, mixed $value): void {
                    $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                    if ($flag === true) {
                        $query->whereNotNull('user_id');
                    } elseif ($flag === false) {
                        $query->whereNull('user_id');
                    }
                }),
                AllowedFilter::dynamic('is_active')->boolean(),
                AllowedFilter::dynamic('joined_at')->date(),
            )
            ->allowedSorts('joined_at', 'last_name', 'is_active')
            ->defaultSort('-joined_at')
            ->with(['user:id,name,email,password', 'user.roles'])
            ->withCount(['scheduledJobs as upcoming_jobs_count' => fn (Builder $q) => $q
                ->whereDate('scheduled_date', '>=', today())
                ->where('status', JobStatusEnum::Planned)])
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (TenantMembership $membership) => EmployeeListItemData::fromModel($membership));
    }

    public function create(EmployeeStoreData $data, User $actor): TenantMembership
    {
        if ($data->has_app_access) {
            Gate::forUser($actor)->authorize('manageAccess', TenantMembership::class);
        }

        $tenantId = current_tenant_id();
        $this->registrar->setPermissionsTeamId($tenantId);

        $attributes = [
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'phone' => $data->phone,
            'position' => $data->position,
            'employee_type' => $data->employee_type,
            ...$this->personalAttributes($data, $actor),
            ...$this->companyAttributes($data->employee_type, $data->company_ico, $data->company_name),
        ];

        return $this->db->transaction(function () use ($data, $actor, $tenantId, $attributes): TenantMembership {
            $membership = null;

            if ($data->has_app_access) {
                $user = User::query()->where('email', $data->email)->first();
                $membership = $user === null ? null : TenantMembership::query()
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $tenantId)
                    ->first();

                if ($membership !== null && $membership->is_active) {
                    throw ValidationException::withMessages(['email' => [__('app.already_member')]]);
                }
            }

            if ($membership !== null) {
                $membership->forceFill(['is_active' => true, ...$attributes])->save();
            } else {
                $membership = TenantMembership::create([
                    'user_id' => null,
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                    'joined_at' => now(),
                    'contact_email' => $data->has_app_access ? null : $data->email,
                    ...$attributes,
                ]);
            }

            if ($data->has_app_access) {
                $this->access->grant($membership, new EmployeeAccessData(
                    email: (string) $data->email,
                    role_name: (string) $data->role_name,
                    permissions: $data->permissions,
                ), $actor);
            }

            if ($data->employment !== null) {
                $template = $this->employmentTemplate($data->employment->employment_type);

                $this->contracts->create(ContractUpsertData::from([
                    'title' => $membership->display_name.' — '.__('app.employment_contract_default_title'),
                    'number' => null,
                    'category' => ContractCategoryEnum::Employment->value,
                    'term_type' => ContractTermTypeEnum::Indefinite->value,
                    'contractable_type' => ContractableTypeEnum::TenantMembership->value,
                    'contractable_id' => $membership->id,
                    'document_template_id' => $template?->id,
                    'body' => $template->body ?? $this->catalogue->skeleton(DocumentTypeEnum::Employment),
                    'valid_from' => today()->toDateString(),
                    'end_date' => null,
                    'notes' => null,
                    'employment' => $data->employment,
                    'show_header' => $template->show_header ?? true,
                    'show_footer' => $template->show_footer ?? true,
                ]), $actor);
            }

            return $membership->load(['user:id,name,email,password', 'user.roles', 'employmentContracts.employmentContract']);
        });
    }

    public function update(TenantMembership $membership, EmployeeUpdateData $data, User $actor): TenantMembership
    {
        $tenantId = current_tenant_id();
        $this->registrar->setPermissionsTeamId($tenantId);

        $role = null;

        if ($membership->hasAppAccess()) {
            /** @var Role $role */
            $role = Role::inTenant($tenantId)->where('name', $data->role_name)->with('permissions')->firstOrFail();
            $this->guard->assertAssignable($actor, [$role]);
            $this->guard->assertPermissionsGrantable($actor, $data->permissions ?? []);
        }

        $attributes = [
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'phone' => $data->phone,
            'position' => $data->position,
            ...($data->employee_type === null ? [] : ['employee_type' => $data->employee_type]),
            ...($membership->hasAppAccess() ? [] : ['contact_email' => $data->contact_email]),
            ...$this->personalAttributes($data, $actor),
            ...$this->companyAttributes($data->employee_type, $data->company_ico, $data->company_name),
        ];

        return $this->db->transaction(function () use ($membership, $data, $role, $attributes): TenantMembership {
            $membership->update($attributes);

            if ($role !== null) {
                $membership->loadMissing('user');
                /** @var User $membershipUser */
                $membershipUser = $membership->user;
                $membershipUser->syncRoles([$role]);
                $membershipUser->syncPermissions($data->permissions ?? []);
            }

            return $membership->load(['user:id,name,email,password', 'user.roles', 'employmentContracts.employmentContract']);
        });
    }

    public function deactivate(TenantMembership $membership): int
    {
        return $this->db->transaction(function () use ($membership): int {
            $membership->update(['is_active' => false]);

            $this->tasks->unassignForMembership($membership);
            $membership->loadMissing('user');

            if ($membership->user !== null) {
                $this->registration->revokePendingInvitations($membership->tenant_id, $membership->user->email);
                $this->sessions->revokeAllIfNoActiveMembership($membership->user);
            }

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

            return $membership->load(['user:id,name,email,password', 'user.roles', 'employmentContracts.employmentContract']);
        });
    }

    /** @return array<string, mixed> */
    private function personalAttributes(EmployeeStoreData|EmployeeUpdateData $data, User $actor): array
    {
        if (! $actor->can(PermissionEnum::ViewEmployeePersonalData->value)) {
            return [];
        }

        return [
            'birth_date' => $data->birth_date,
            'national_id' => $this->normalizeNationalId($data->national_id),
            'street' => $data->street,
            'city' => $data->city,
            'postal_code' => $data->postal_code,
        ];
    }

    /** @return array<string, string|null> */
    private function companyAttributes(?EmployeeTypeEnum $type, ?string $ico, ?string $name): array
    {
        return match ($type) {
            EmployeeTypeEnum::SelfEmployed => ['company_ico' => $ico, 'company_name' => $name],
            EmployeeTypeEnum::Brigade, EmployeeTypeEnum::Tpp => ['company_ico' => null, 'company_name' => null],
            null => [],
        };
    }

    private function normalizeNationalId(?string $nationalId): ?string
    {
        if ($nationalId === null || trim($nationalId) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $nationalId) ?? '';

        return substr($digits, 0, 6).'/'.substr($digits, 6);
    }

    private function employmentTemplate(EmploymentContractTypeEnum $employmentType): ?DocumentTemplate
    {
        $options = collect($this->templates->options(DocumentTypeEnum::Employment, $employmentType));

        return $options->firstWhere('employment_type', $employmentType) ?? $options->first();
    }
}
