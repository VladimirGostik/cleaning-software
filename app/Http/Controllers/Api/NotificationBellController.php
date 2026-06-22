<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\Notifications\NotificationBellData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

final class NotificationBellController extends Controller
{
    // No #[Authorize] — mirrors MeController; auth + TenantContextMiddleware are the gates; self-scoped.
    public function __invoke(Request $request, NotificationService $service): NotificationBellData
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = (string) app('current_tenant_id');

        return $service->bell($user, $tenantId);
    }
}
