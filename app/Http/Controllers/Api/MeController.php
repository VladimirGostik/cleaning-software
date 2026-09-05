<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Auth\MeData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    // No #[Authorize]: returns the caller's own capabilities — auth middleware is the gate, no record-level authz for self.
    public function __invoke(Request $request): MeData
    {
        /** @var User $user */
        $user = $request->user();

        $activeTenantId = app()->bound('current_tenant_id')
            ? app('current_tenant_id')
            : null;

        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

        return new MeData(
            userId: $user->id,
            activeTenantId: $activeTenantId,
            permissions: $permissions,
        );
    }
}
