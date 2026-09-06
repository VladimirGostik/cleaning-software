<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every authenticated route (except logout) requires a bound tenant. A user whose
 * active membership was deactivated mid-session gets logged out here rather than
 * running unscoped (TenantScope does not filter when nothing is bound — see D6).
 */
final class RequireActiveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null || app()->bound('current_tenant_id')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('app.no_active_tenant')], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('error', __('app.no_active_tenant'));
    }
}
