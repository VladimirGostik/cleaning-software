<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\ChecksFeatures;
use App\Data\Auth\MeData;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

final class MeController extends Controller
{
    // No #[Authorize]: returns the caller's own capabilities — auth middleware is the gate, no record-level authz for self.
    public function __invoke(Request $request, ChecksFeatures $checker): MeData
    {
        /** @var User $user */
        $user = $request->user();

        $activeTenantId = app()->bound('current_tenant_id')
            ? app('current_tenant_id')
            : null;

        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

        if ($activeTenantId !== null) {
            $tenant = Tenant::find($activeTenantId);
            $features = $tenant !== null ? $checker->featuresFor($tenant) : [];
        } else {
            $features = [];
        }

        return new MeData(
            userId: $user->id,
            activeTenantId: $activeTenantId,
            permissions: $permissions,
            features: $features,
        );
    }
}
