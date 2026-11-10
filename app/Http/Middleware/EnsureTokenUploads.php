<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Ai\Guard\PromptGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureTokenUploads
{
    public function __construct(private PromptGuard $guard) {}

    public function handle(Request $request, Closure $next, string ...$modes): Response
    {
        if (! config()->boolean('token_uploads.enabled')) {
            abort(404);
        }

        $user = $request->user();

        if (in_array('write', $modes, true) && $user instanceof User && $this->guard->isMcpRestricted($user)) {
            abort(423, __('app.mcp_guard_blocked'));
        }

        /** @var Response */
        return $next($request);
    }
}
