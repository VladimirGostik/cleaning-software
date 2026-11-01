<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Profile\McpTokenCreateData;
use App\Models\User;
use App\Services\McpTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Laravel\Sanctum\PersonalAccessToken;

final class ProfileMcpTokenController extends Controller
{
    public function __construct(private readonly McpTokenService $tokens) {}

    #[Authorize('create', PersonalAccessToken::class)]
    public function store(McpTokenCreateData $data, Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $created = $this->tokens->create($user, $data);

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return response()->json($created, 201);
        }

        Inertia::flash('mcpToken', $created->toArray());

        return back()->with('success', __('app.mcp_token_created'));
    }

    public function destroy(int $token, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $own = $this->tokens->findOwnMcpToken($user, $token);

        Gate::forUser($user)->authorize('delete', $own);

        $this->tokens->revoke($own);

        return back()->with('success', __('app.mcp_token_revoked'));
    }
}
