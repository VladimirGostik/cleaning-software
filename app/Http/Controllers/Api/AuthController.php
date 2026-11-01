<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\AuthTokenData;
use App\Data\LoginData;
use App\Data\MobileTokenPairData;
use App\Data\RefreshTokenData;
use App\Enums\MobileSessionRevokeReasonEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\MobileSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Laravel\Sanctum\PersonalAccessToken;

#[Group('Auth', 'Authentication')]
final class AuthController extends Controller
{
    public function __construct(private readonly MobileSessionService $sessions) {}

    #[Unauthenticated]
    #[Endpoint('Login', 'Authenticate and receive a 60-minute access token plus a refresh token that stays valid until one year of non-use.')]
    #[ResponseFromSpatieData(AuthTokenData::class, User::class, with: ['roles'])]
    #[Response(['message' => 'The provided credentials are incorrect.'], 422, 'Invalid credentials')]
    public function login(LoginData $data): JsonResponse
    {
        if (! Auth::attempt(['email' => $data->email, 'password' => $data->password, 'is_active' => true])) {
            throw ValidationException::withMessages([
                'email' => [__('app.invalid_credentials')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        $denial = $this->sessions->mobileAccessDenial($user);

        if ($denial !== null) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => [__($denial)],
            ]);
        }

        $user->load('roles');

        return response()->json(AuthTokenData::make($user, $this->sessions->issue($user)));
    }

    #[Unauthenticated]
    #[Endpoint('Refresh tokens', 'Exchange a refresh token for a new access and refresh token. Only 401 means the session is dead; retrying the same refresh token after a timeout is safe.')]
    #[Response(['token' => '1|example_sanctum_token_here', 'refresh_token' => '0199e0f0-0000-7000-8000-000000000000.example_refresh_secret'], 200, 'New token pair')]
    #[Response(['message' => 'The session is no longer valid. Sign in again.'], 401, 'Session dead')]
    public function refresh(RefreshTokenData $data): JsonResponse
    {
        $pair = $this->sessions->refresh($data->refresh_token);

        if (! $pair instanceof MobileTokenPairData) {
            return response()->json(['message' => __('app.session_invalid')], 401);
        }

        return response()->json($pair);
    }

    #[Unauthenticated]
    #[Endpoint('Revoke session', 'Logs the phone out with its refresh token, even when the access token has expired. Always 204 for a well-formed body.')]
    #[Response(null, 204, 'Revoked or nothing to revoke')]
    public function revoke(RefreshTokenData $data): JsonResponse
    {
        $this->sessions->revokeByRefreshToken($data->refresh_token);

        return response()->json(null, 204);
    }

    #[Authenticated]
    #[Endpoint('Logout', 'Revoke the current Bearer token and, for a mobile token, its whole session.')]
    #[Response(null, 204, 'Logged out')]
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $session = $this->sessions->currentFor($user);

        if ($session !== null) {
            $this->sessions->revoke($session, MobileSessionRevokeReasonEnum::Logout);
        } elseif (($token = $user->currentAccessToken()) instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(null, 204);
    }
}
