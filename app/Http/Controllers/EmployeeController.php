<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Employees\EmployeeDetailData;
use App\Data\Employees\EmployeeDocumentConstraintsData;
use App\Data\Employees\EmployeeDocumentData;
use App\Data\Employees\EmployeeFormContextData;
use App\Data\Employees\EmployeePersonalData;
use App\Data\Employees\EmployeePersonalFormData;
use App\Data\Employees\EmployeeRoleData;
use App\Data\Employees\EmployeeStoreData;
use App\Data\Employees\EmployeeUpdateData;
use App\Data\RoleListItemData;
use App\Enums\JobStatusEnum;
use App\Enums\NavIconEnum;
use App\Enums\PermissionEnum;
use App\Models\Document;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\ContractService;
use App\Services\EmployeeService;
use App\Services\Expenses\EmployeeExpenseService;
use App\Services\RoleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employees,
        private readonly RoleService $roles,
        private readonly ContractService $contracts,
        private readonly EmployeeExpenseService $employeeExpenses,
    ) {}

    #[Authorize('viewAny', TenantMembership::class)]
    #[NavItem(label: 'app.employees', route: 'employees.index', icon: NavIconEnum::Team, permission: PermissionEnum::ViewEmployees->value, order: 20)]
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
    public function create(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Employees/Create', [
            'context' => $this->formContext(),
            'personal' => $actor->can(PermissionEnum::ViewEmployeePersonalData->value) ? EmployeePersonalFormData::blank() : null,
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
            'deactivate' => $employee->is_active && $actor->can('delete', $employee),
            'reactivate' => ! $employee->is_active && $actor->can('reactivate', $employee),
            'assign_role' => $actor->can('assignRole', $employee),
            'manage_documents' => $actor->can('attach', [Document::class, $employee]),
            'grant_access' => $actor->can('grantAccess', $employee),
            'revoke_access' => $actor->can('revokeAccess', $employee),
            'resend_invitation' => $actor->can('resendInvitation', $employee),
        ];

        $canViewDocuments = $actor->can(PermissionEnum::ViewEmployeeDocuments->value)
            && $actor->can(PermissionEnum::ViewEmployees->value);

        $documents = $canViewDocuments
            ? $employee->documents()->with('media')->latest()->get()
                ->map(fn (Document $document) => EmployeeDocumentData::fromModel($document, $employee->id))
                ->all()
            : null;

        return Inertia::render('Employees/Show', [
            'employee' => EmployeeDetailData::fromModel($employee, $can),
            'roleOptions' => ($can['assign_role'] || $can['grant_access']) ? $this->roleOptions() : [],
            'personal' => $actor->can(PermissionEnum::ViewEmployeePersonalData->value) ? EmployeePersonalData::fromModel($employee) : null,
            'documents' => $documents,
            'documentConstraints' => $can['manage_documents'] ? EmployeeDocumentConstraintsData::fromConfig() : null,
            'contracts' => $this->contracts->listFor($employee, $actor),
            'expenses' => $actor->can(PermissionEnum::ViewExpenses->value) && $actor->can(PermissionEnum::ViewEmployeePersonalData->value)
                ? $this->employeeExpenses->forEmployee($employee)
                : null,
            'canCreateContract' => $this->contracts->canCreateFor($employee, $actor),
        ]);
    }

    #[Authorize('update', 'employee')]
    public function edit(TenantMembership $employee, Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        Inertia::encryptHistory();

        $employee->load(['user', 'user.roles', 'employmentContracts.employmentContract', 'tenant']);

        $personal = null;

        if ($actor->can(PermissionEnum::ViewEmployeePersonalData->value)) {
            $personal = EmployeePersonalFormData::fromModel($employee);

            activity()
                ->performedOn($employee)
                ->causedBy($actor)
                ->event('national_id_viewed')
                ->log('national_id_viewed');
        }

        return Inertia::render('Employees/Edit', [
            'employee' => EmployeeDetailData::fromModel($employee),
            'context' => $this->formContext(),
            'personal' => $personal,
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
            can_manage_access: Gate::allows('manageAccess', TenantMembership::class),
        );
    }
}
