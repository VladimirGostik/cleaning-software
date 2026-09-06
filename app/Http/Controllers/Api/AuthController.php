<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\AuthTokenData;
use App\Data\LoginData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group('Auth', 'Authentication')]
final class AuthController extends Controller
{
    #[Unauthenticated]
    #[Endpoint('Login', 'Authenticate and receive a Sanctum Bearer token.')]
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

        if (! $user->hasActiveMembership()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => [__('app.no_active_tenant')],
            ]);
        }

        $user->load('roles');

        return response()->json(AuthTokenData::make($user, $user->createToken('api')->plainTextToken));
    }

    #[Authenticated]
    #[Endpoint('Logout', 'Revoke the current Bearer token.')]
    #[Response(null, 204, 'Logged out')]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
