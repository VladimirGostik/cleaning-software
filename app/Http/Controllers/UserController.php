<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\CreateUserData;
use App\Data\RoleListItemData;
use App\Data\UpdateUserData;
use App\Data\UserAutocompleteItemData;
use App\Data\UserListItemData;
use App\Enums\PermissionEnum;
use App\Models\Role;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\UserService;
use App\Utils\AllowedFilter;
use App\Utils\SymbolOperators;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
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
    #[NavItem(label: 'app.users', route: 'users.index', icon: 'UsersIcon', permission: PermissionEnum::ViewEmployees->value, order: 20)]
    public function index(Request $request): Response
    {
        $tenantId = current_tenant_id();

        $users = QueryBuilder::for($this->memberBaseQuery($tenantId))
            ->allowedFilters(
                AllowedFilter::search(['name', 'email']),
                AllowedFilter::dynamic('name'),
                AllowedFilter::dynamic('email'),
                AllowedFilter::callback('is_active', function (Builder $query, mixed $value) use ($tenantId): void {
                    if (is_array($value)) {
                        return;
                    }

                    [$operator, $normalized] = SymbolOperators::parse($value);

                    if ($normalized === null || $normalized === '') {
                        return;
                    }

                    $bool = filter_var($normalized, FILTER_VALIDATE_BOOLEAN);
                    $method = $operator === '!=' ? 'whereDoesntHave' : 'whereHas';

                    $query->{$method}('memberships', fn (Builder $q) => $q->where('tenant_id', $tenantId)->where('is_active', $bool));
                }),
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
                'roles' => $this->getRoleOptions($tenantId),
            ],
        ]);
    }

    #[Authorize('create', User::class)]
    public function create(): Response
    {
        return Inertia::render('Users/Form', [
            'roles' => $this->getRoleOptions(current_tenant_id()),
        ]);
    }

    #[Authorize('create', User::class)]
    public function store(CreateUserData $data, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->userService->create($data, $actor);

        return redirect()->route('users.index')->with('success', __('app.user_created'));
    }

    #[Authorize('update', 'user')]
    public function edit(User $user): Response
    {
        $tenantId = current_tenant_id();
        $user->load(['roles', 'memberships' => fn (Relation $q) => $q->where('tenant_id', $tenantId)]);

        return Inertia::render('Users/Form', [
            'user' => UserListItemData::fromModel($user),
            'roles' => $this->getRoleOptions($tenantId),
        ]);
    }

    #[Authorize('update', 'user')]
    public function update(UpdateUserData $data, User $user, Request $request): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->userService->update($user, $data, $actor);

        return redirect()->route('users.index')->with('success', __('app.user_updated'));
    }

    #[Authorize('delete', 'user')]
    public function destroy(User $user): RedirectResponse
    {
        $this->userService->delete($user);

        return redirect()->route('users.index')->with('success', __('app.user_deleted'));
    }

    private const int AUTOCOMPLETE_MIN_CHARS = 2;

    #[Authorize('viewAny', User::class)]
    public function autocomplete(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q !== '' && mb_strlen($q) < self::AUTOCOMPLETE_MIN_CHARS) {
            return response()->json([]);
        }

        $tenantId = current_tenant_id();

        $users = $this->memberBaseQuery($tenantId)
            ->whereHas('memberships', fn (Builder $q) => $q->where('tenant_id', $tenantId)->where('is_active', true))
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

    /** @return Builder<User> */
    private function memberBaseQuery(string $tenantId): Builder
    {
        return User::query()
            ->whereHas('memberships', fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->with(['roles', 'memberships' => fn (Relation $q) => $q->where('tenant_id', $tenantId)]);
    }

    /** @return array<int, RoleListItemData> */
    private function getRoleOptions(string $tenantId): array
    {
        return Role::inTenant($tenantId)
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => RoleListItemData::fromModel($role))
            ->all();
    }
}
