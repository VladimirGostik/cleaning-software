<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\UpdateUserData;
use App\Data\UserListItemData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\UserService;
use App\Utils\AllowedFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\QueryParam;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\UrlParam;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Users', 'User management')]
#[Authenticated]
final class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    #[Authorize('viewAny', User::class)]
    #[Endpoint('List users', 'Returns a paginated list of users with optional filtering and sorting.')]
    #[QueryParam('filter[search]', 'string', 'Search across name and email.', required: false, example: 'john')]
    #[QueryParam('filter[name]', 'string', 'Filter by name (supports operators: ~, !=).', required: false, example: 'John')]
    #[QueryParam('filter[email]', 'string', 'Filter by email (supports operators: ~, !=).', required: false, example: 'john@example.com')]
    #[QueryParam('filter[is_active]', 'boolean', 'Filter by active status.', required: false, example: true)]
    #[QueryParam('filter[role]', 'string', 'Filter by exact role name.', required: false, example: 'admin')]
    #[QueryParam('filter[created_at]', 'string', 'Filter by creation date (supports operators: >, <, >=, <=).', required: false, example: '>2026-01-01')]
    #[QueryParam('sort', 'string', 'Sort field. Prefix with `-` for descending. Allowed: name, email, is_active, created_at, updated_at.', required: false, example: '-created_at')]
    #[QueryParam('per_page', 'integer', 'Number of results per page (default: 25).', required: false, example: 25)]
    #[ResponseFromSpatieData(UserListItemData::class, User::class, with: ['roles'], paginated: true)]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    public function index(Request $request): JsonResponse
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
            ->allowedSorts('name', 'email', 'is_active', 'created_at', 'updated_at')
            ->defaultSort('name')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString()
            ->through(fn (User $user) => UserListItemData::fromModel($user));

        return response()->json($users);
    }

    #[Authorize('view', 'user')]
    #[Endpoint('Get user', 'Returns a single user by ID.')]
    #[UrlParam('user', 'string', 'The UUID of the user.', example: '0195d123-0000-7000-0000-000000000001')]
    #[ResponseFromSpatieData(UserListItemData::class, User::class, with: ['roles'])]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'User not found')]
    public function show(User $user): JsonResponse
    {
        $user->load('roles');

        return response()->json(UserListItemData::fromModel($user));
    }

    #[Authorize('update', 'user')]
    #[Endpoint('Update user', 'Updates an existing user. Replaces the full set of roles.')]
    #[UrlParam('user', 'string', 'The UUID of the user.', example: '0195d123-0000-7000-0000-000000000001')]
    #[ResponseFromSpatieData(UserListItemData::class, User::class, with: ['roles'])]
    #[Response(null, 422, 'Validation error')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized')]
    #[Response(null, 404, 'User not found')]
    public function update(UpdateUserData $data, User $user): JsonResponse
    {
        $user = $this->userService->update($user, $data);

        return response()->json(UserListItemData::fromModel($user));
    }

    #[Authorize('delete', 'user')]
    #[Endpoint('Delete user', 'Permanently deletes a user. Cannot delete your own account.')]
    #[UrlParam('user', 'string', 'The UUID of the user.', example: '0195d123-0000-7000-0000-000000000001')]
    #[Response(null, 204, 'User deleted')]
    #[Response(null, 401, 'Unauthenticated')]
    #[Response(null, 403, 'Unauthorized (or attempting self-deletion)')]
    #[Response(null, 404, 'User not found')]
    public function destroy(User $user): JsonResponse
    {
        $this->userService->delete($user);

        return response()->json(null, 204);
    }
}
