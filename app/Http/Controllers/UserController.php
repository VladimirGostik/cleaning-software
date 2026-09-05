<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\CreateUserData;
use App\Data\RoleListItemData;
use App\Data\UpdateUserData;
use App\Data\UserAutocompleteItemData;
use App\Data\UserListItemData;
use App\Models\Role;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\RoleService;
use App\Services\UserService;
use App\Utils\AllowedFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    #[Authorize('viewAny', User::class)]
    #[NavItem(label: 'app.users', route: 'users.index', icon: 'UsersIcon', permission: 'view users', order: 20)]
    public function index(Request $request): Response
    {
        $users = QueryBuilder::for(User::query()->with('roles'))
            ->allowedFilters(
                AllowedFilter::search(['name', 'email']),
                AllowedFilter::dynamic('name'),
                AllowedFilter::dynamic('email'),
                AllowedFilter::dynamic('is_active')->boolean(),
                AllowedFilter::relationExact('role', 'roles', 'name'),
                AllowedFilter::dynamic('created_at')->date(),
            )
            ->allowedSorts(
                'name',
                'email',
                'is_active',
                'created_at',
                'updated_at',
            )
            ->defaultSort('name')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (User $user) => UserListItemData::fromModel($user));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->query(),
            'filterOptions' => [
                'roles' => $this->getRoleOptions(),
            ],
        ]);
    }

    #[Authorize('create', User::class)]
    public function create(): Response
    {
        return Inertia::render('Users/Form', [
            'roles' => $this->getRoleOptions(),
        ]);
    }

    #[Authorize('create', User::class)]
    public function store(CreateUserData $data): RedirectResponse
    {
        $this->userService->create($data);

        return redirect()->route('users.index')->with('success', __('app.user_created'));
    }

    #[Authorize('update', 'user')]
    public function edit(User $user): Response
    {
        $user->load('roles');

        return Inertia::render('Users/Form', [
            'user' => UserListItemData::fromModel($user),
            'roles' => $this->getRoleOptions(),
        ]);
    }

    #[Authorize('update', 'user')]
    public function update(UpdateUserData $data, User $user): RedirectResponse
    {
        $this->userService->update($user, $data);

        return redirect()->route('users.index')->with('success', __('app.user_updated'));
    }

    #[Authorize('delete', 'user')]
    public function destroy(User $user): RedirectResponse
    {
        $this->userService->delete($user);

        return redirect()->route('users.index')->with('success', __('app.user_deleted'));
    }

    #[Authorize('viewAny', User::class)]
    public function autocomplete(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->where('is_active', true)
            ->when($q !== '', function ($qb) use ($q) {
                $like = config('database.default') === 'pgsql' ? 'ilike' : 'like';
                $needle = '%'.addcslashes($q, '%_\\').'%';
                $qb->where(fn ($inner) => $inner
                    ->where('name', $like, $needle)
                    ->orWhere('email', $like, $needle));
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(
            $users->map(fn (User $user) => UserAutocompleteItemData::fromModel($user))->toArray(),
        );
    }

    /** @return array<int, RoleListItemData> */
    private function getRoleOptions(): array
    {
        return Role::withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => new RoleListItemData(
                id: $role->id,
                name: $role->name,
                permissions_count: (int) $role->permissions_count,
                users_count: (int) $role->users_count,
                is_system: in_array($role->name, RoleService::SYSTEM_ROLES, true),
            ))
            ->toArray();
    }
}
