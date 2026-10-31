<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Dashboard\DashboardData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;

/** Mobile twin of the web `/` overview — every metric group is already nulled per missing per-tenant permission. */
#[Group('Dashboard', 'Cross-company overview')]
#[Authenticated]
final class DashboardController extends Controller
{
    #[Endpoint('Dashboard', 'Cross-company metrics and alerts for the authenticated user.')]
    #[ResponseFromSpatieData(DashboardData::class, User::class)]
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($dashboard->overviewFor($user));
    }
}
