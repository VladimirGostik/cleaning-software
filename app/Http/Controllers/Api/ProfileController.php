<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\ChangePasswordData;
use App\Data\UpdateProfileData;
use App\Data\UserListItemData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Scribe\Attributes\ResponseFromSpatieData;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

#[Group('Profile', 'Authenticated user profile')]
#[Authenticated]
final class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    #[Endpoint('Get profile', 'Returns the authenticated user\'s profile.')]
    #[ResponseFromSpatieData(UserListItemData::class, User::class, with: ['roles'])]
    #[Response(null, 401, 'Unauthenticated')]
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('roles');

        return response()->json(UserListItemData::fromModel($user));
    }

    #[Endpoint('Update profile', 'Update name, email and locale of the authenticated user.')]
    #[ResponseFromSpatieData(UserListItemData::class, User::class, with: ['roles'])]
    #[Response(null, 422, 'Validation error')]
    #[Response(null, 401, 'Unauthenticated')]
    public function update(UpdateProfileData $data, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user = $this->profileService->updateProfile($user, $data);
        $user->load('roles');

        return response()->json(UserListItemData::fromModel($user));
    }

    #[Endpoint('Change password', 'Change the authenticated user\'s password.')]
    #[Response(null, 204, 'Password changed')]
    #[Response(['message' => 'The current password is incorrect.'], 422, 'Wrong current password')]
    #[Response(null, 401, 'Unauthenticated')]
    public function changePassword(ChangePasswordData $data, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data->current_password, $user->password ?? '')) {
            throw ValidationException::withMessages([
                'current_password' => [__('app.invalid_current_password')],
            ]);
        }

        $user->update(['password' => $data->password]);

        return response()->json(null, 204);
    }
}
