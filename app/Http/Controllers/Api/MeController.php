<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Auth\MeData;
use App\Data\Auth\MobileCompanyData;
use App\Enums\MobileAppModeEnum;
use App\Enums\SupportedLanguage;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\Dashboard\TenantVisibility;
use App\Services\Tenancy\TenantPermissionSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function __invoke(Request $request, TenantPermissionSnapshot $snapshot): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $visibilities = $snapshot->for($user);
        $companies = array_map(MobileCompanyData::fromVisibility(...), $visibilities);
        $activeTenantId = current_tenant_id();
        $active = array_find($companies, fn (MobileCompanyData $company): bool => $company->tenant_id === $activeTenantId);

        return response()->json(new MeData(
            userId: $user->id,
            name: $user->name,
            email: $user->email,
            locale: SupportedLanguage::tryFrom($user->locale) ?? SupportedLanguage::Slovak,
            activeTenantId: $activeTenantId,
            permissions: $active->permissions ?? [],
            companies: $companies,
            mode: MobileAppModeEnum::fromVisibilities(array_values(array_filter($visibilities, static fn (TenantVisibility $visibility): bool => $visibility->mobileAccess))),
        ));
    }
}
