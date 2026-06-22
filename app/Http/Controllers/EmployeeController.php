<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Employees\EmployeeDetailData;
use App\Data\Employees\EmployeeIndexFilterData;
use App\Data\Employees\EmployeeListItemData;
use App\Data\Employees\EmployeeUpsertData;
use App\Enums\EmploymentContractTypeEnum;
use App\Enums\PermissionEnum;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\LaravelData\PaginatedDataCollection;

final class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $employees) {}

    #[Authorize('viewAny', TenantMembership::class)]
    public function index(EmployeeIndexFilterData $filter): InertiaResponse
    {
        $paginator = $this->employees->paginate($filter);

        return Inertia::render('Employees/Index', [
            'employees' => EmployeeListItemData::collect(
                $paginator->through(fn (TenantMembership $m) => EmployeeListItemData::fromModel($m)),
                PaginatedDataCollection::class,
            ),
            'filters' => $filter,
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    #[Authorize('create', TenantMembership::class)]
    public function create(): InertiaResponse
    {
        return Inertia::render('Employees/Create', [
            'roleOptions' => $this->roleOptions(),
            'permissionGroups' => $this->permissionGroups(),
            'employmentTypeOptions' => EmploymentContractTypeEnum::options(),
        ]);
    }

    #[Authorize('create', TenantMembership::class)]
    public function store(EmployeeUpsertData $data): RedirectResponse
    {
        $membership = $this->employees->create($data);

        return redirect()->route('employees.show', $membership->id)
            ->with('flash', ['success' => __('app.employees.created')]);
    }

    #[Authorize('view', 'employee')]
    public function show(TenantMembership $employee): InertiaResponse
    {
        $employee->load(['user', 'user.roles', 'employmentContracts.employmentContract']);

        return Inertia::render('Employees/Show', [
            'employee' => EmployeeDetailData::fromModel($employee),
        ]);
    }

    #[Authorize('update', 'employee')]
    public function edit(TenantMembership $employee): InertiaResponse
    {
        $employee->load(['user', 'user.roles', 'employmentContracts.employmentContract']);

        return Inertia::render('Employees/Edit', [
            'employee' => EmployeeDetailData::fromModel($employee),
            'roleOptions' => $this->roleOptions(),
            'permissionGroups' => $this->permissionGroups(),
            'employmentTypeOptions' => EmploymentContractTypeEnum::options(),
        ]);
    }

    #[Authorize('update', 'employee')]
    public function update(TenantMembership $employee, EmployeeUpsertData $data): RedirectResponse
    {
        $this->employees->update($employee, $data);

        return redirect()->route('employees.show', $employee->id)
            ->with('flash', ['success' => __('app.employees.updated')]);
    }

    #[Authorize('delete', 'employee')]
    public function deactivate(TenantMembership $employee): RedirectResponse
    {
        $this->employees->deactivate($employee);

        return redirect()->route('employees.index')
            ->with('flash', ['success' => __('app.employees.deactivated')]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return Role::where('tenant_id', app('current_tenant_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $r) => ['value' => $r->name, 'label' => $r->name])
            ->all();
    }

    /**
     * @return array<int, array{module: string, permissions: list<array{value: string, label: string}>}>
     */
    private function permissionGroups(): array
    {
        $groups = [
            'clients' => [PermissionEnum::ViewClients, PermissionEnum::CreateClients, PermissionEnum::EditClients, PermissionEnum::DeleteClients],
            'objects' => [PermissionEnum::ViewObjects, PermissionEnum::CreateObjects, PermissionEnum::EditObjects, PermissionEnum::DeleteObjects],
            'quotes' => [PermissionEnum::ViewQuotes, PermissionEnum::CreateQuotes, PermissionEnum::EditQuotes, PermissionEnum::SendQuotes, PermissionEnum::ApproveQuotes, PermissionEnum::DeleteQuotes],
            'contracts' => [PermissionEnum::ViewContracts, PermissionEnum::CreateContracts, PermissionEnum::EditContracts, PermissionEnum::TerminateContracts, PermissionEnum::DeleteContracts],
            'employees' => [PermissionEnum::ViewEmployees, PermissionEnum::CreateEmployees, PermissionEnum::EditEmployees, PermissionEnum::AssignEmployees, PermissionEnum::DeleteEmployees],
            'invoices' => [PermissionEnum::ViewInvoices, PermissionEnum::CreateInvoices, PermissionEnum::EditInvoices, PermissionEnum::CancelInvoices],
            'schedule' => [PermissionEnum::ViewSchedule, PermissionEnum::CreateSchedule, PermissionEnum::EditSchedule, PermissionEnum::AssignCleaners],
        ];

        $result = [];

        foreach ($groups as $module => $cases) {
            $result[] = [
                'module' => $module,
                'permissions' => array_map(
                    fn (PermissionEnum $p) => ['value' => $p->value, 'label' => $p->label()],
                    $cases,
                ),
            ];
        }

        return $result;
    }
}
