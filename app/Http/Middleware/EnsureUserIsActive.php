<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsActive
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_active === false) {
            return response()->json(['message' => __('app.account_inactive')], 403);
        }

        return $next($request);
    }
}
