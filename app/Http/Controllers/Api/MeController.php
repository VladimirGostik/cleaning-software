<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Auth\MeData;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

/**
 * Returns the caller's own capabilities for the active tenant — no `#[Authorize]`
 * needed, every authenticated user may read their own permission set.
 */
#[Group('Auth', 'Authentication')]
#[Authenticated]
final class MeController extends Controller
{
    #[Endpoint('Me', 'Returns the authenticated user\'s id, active tenant and permissions.')]
    #[ResponseFromSpatieData(MeData::class, User::class)]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Collection<int, Permission> $allPermissions */
        $allPermissions = $user->getAllPermissions();

        /** @var list<PermissionEnum> $permissions */
        $permissions = array_values($allPermissions
            ->map(fn (Permission $permission): PermissionEnum => PermissionEnum::from($permission->name))
            ->all());

        return response()->json(new MeData(
            userId: $user->id,
            activeTenantId: current_tenant_id(),
            permissions: $permissions,
        ));
    }
}
