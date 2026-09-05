<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\CreateRoleData;
use App\Data\RoleDetailData;
use App\Data\RoleListItemData;
use App\Data\UpdateRoleData;
use App\Models\Role;
use App\Navigation\NavItem;
use App\Services\RoleService;
use App\Utils\AllowedFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Spatie\QueryBuilder\QueryBuilder;

final class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    #[Authorize('viewAny', Role::class)]
    #[NavItem(label: 'app.roles', route: 'roles.index', icon: 'ShieldCheckIcon', permission: 'view roles', order: 30)]
    public function index(Request $request): Response
    {
        $roles = QueryBuilder::for(Role::class)
            ->allowedFilters(
                AllowedFilter::search(['name']),
            )
            ->withCount(['permissions', 'users'])
            ->defaultSort('name')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (Role $role) => new RoleListItemData(
                id: $role->id,
                name: $role->name,
                permissions_count: (int) $role->permissions_count,
                users_count: (int) $role->users_count,
                is_system: in_array($role->name, RoleService::SYSTEM_ROLES, true),
            ));

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'filters' => $request->query(),
        ]);
    }

    #[Authorize('create', Role::class)]
    public function create(): Response
    {
        return Inertia::render('Roles/Form', [
            'permissions' => $this->roleService->getPermissionsGrouped(),
        ]);
    }

    #[Authorize('create', Role::class)]
    public function store(CreateRoleData $data): RedirectResponse
    {
        try {
            $this->roleService->create($data->name, $data->permissions);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('roles.index')->with('success', __('app.role_created'));
    }

    #[Authorize('update', 'role')]
    public function edit(Role $role): Response
    {
        $role->load('permissions');

        return Inertia::render('Roles/Form', [
            'role' => RoleDetailData::fromModel($role),
            'permissions' => $this->roleService->getPermissionsGrouped(),
        ]);
    }

    #[Authorize('update', 'role')]
    public function update(UpdateRoleData $data, Role $role): RedirectResponse
    {
        try {
            $this->roleService->update($role, $data->name, $data->permissions);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('roles.index')->with('success', __('app.role_updated'));
    }

    #[Authorize('delete', 'role')]
    public function destroy(Role $role): RedirectResponse
    {
        try {
            $this->roleService->delete($role);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('roles.index')->with('success', __('app.role_deleted'));
    }
}
