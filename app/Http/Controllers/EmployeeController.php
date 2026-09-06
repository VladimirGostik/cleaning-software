<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Employees\EmployeeDetailData;
use App\Data\Employees\EmployeeFormContextData;
use App\Data\Employees\EmployeeRoleData;
use App\Data\Employees\EmployeeStoreData;
use App\Data\Employees\EmployeeUpdateData;
use App\Data\RoleListItemData;
use App\Enums\JobStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\EmployeeService;
use App\Services\RoleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

final class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employees,
        private readonly RoleService $roles,
    ) {}

    #[Authorize('viewAny', TenantMembership::class)]
    #[NavItem(label: 'app.employees', route: 'employees.index', icon: 'IdentificationIcon', permission: PermissionEnum::ViewEmployees->value, order: 20)]
    public function index(Request $request): Response
    {
        return Inertia::render('Employees/Index', [
            'employees' => $this->employees->paginate($request),
            'filters' => $request->query(),
            'filterOptions' => [
                'roles' => $this->roleOptions(),
            ],
        ]);
    }

    #[Authorize('create', TenantMembership::class)]
    public function create(): Response
    {
        return Inertia::render('Employees/Create', [
            'context' => $this->formContext(),
        ]);
    }

    #[Authorize('create', TenantMembership::class)]
    public function store(EmployeeStoreData $data, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $membership = $this->employees->create($data, $actor);

        return to_route('employees.show', $membership)->with('success', __('app.employee_created'));
    }

    #[Authorize('view', 'employee')]
    public function show(TenantMembership $employee, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $employee->load(['user', 'user.roles', 'employmentContracts.employmentContract', 'tenant']);
        $employee->loadCount(['scheduledJobs as upcoming_jobs_count' => fn (Builder $q) => $q
            ->whereDate('scheduled_date', '>=', today())
            ->where('status', JobStatusEnum::Planned)]);

        $can = [
            'update' => $actor->can('update', $employee),
            'deactivate' => $actor->can('delete', $employee),
            'reactivate' => $actor->can('reactivate', $employee),
            'assign_role' => $actor->can('assignRole', $employee),
        ];

        return Inertia::render('Employees/Show', [
            'employee' => EmployeeDetailData::fromModel($employee, $can),
            'roleOptions' => $can['assign_role'] ? $this->roleOptions() : [],
        ]);
    }

    #[Authorize('update', 'employee')]
    public function edit(TenantMembership $employee): Response
    {
        $employee->load(['user', 'user.roles', 'employmentContracts.employmentContract', 'tenant']);

        return Inertia::render('Employees/Edit', [
            'employee' => EmployeeDetailData::fromModel($employee),
            'context' => $this->formContext(),
        ]);
    }

    #[Authorize('update', 'employee')]
    public function update(EmployeeUpdateData $data, TenantMembership $employee, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->employees->update($employee, $data, $actor);

        return to_route('employees.show', $employee)->with('success', __('app.employee_updated'));
    }

    #[Authorize('delete', 'employee')]
    public function deactivate(TenantMembership $employee): RedirectResponse
    {
        $unassigned = $this->employees->deactivate($employee);

        $message = $unassigned > 0
            ? __('app.employee_deactivated_with_jobs', ['count' => $unassigned])
            : __('app.employee_deactivated');

        return to_route('employees.show', $employee)->with('success', $message);
    }

    #[Authorize('reactivate', 'employee')]
    public function reactivate(TenantMembership $employee): RedirectResponse
    {
        $this->employees->reactivate($employee);

        return to_route('employees.show', $employee)->with('success', __('app.employee_reactivated'));
    }

    #[Authorize('assignRole', 'employee')]
    public function role(EmployeeRoleData $data, TenantMembership $employee, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->employees->assignRole($employee, $data->role_name, $actor);

        return to_route('employees.show', $employee)->with('success', __('app.employee_updated'));
    }

    /** @return array<int, RoleListItemData> */
    private function roleOptions(): array
    {
        return Role::inTenant(current_tenant_id())
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => RoleListItemData::fromModel($role))
            ->all();
    }

    private function formContext(): EmployeeFormContextData
    {
        return new EmployeeFormContextData(
            roles: $this->roleOptions(),
            permission_groups: $this->roles->getPermissionsGrouped(),
        );
    }
}
