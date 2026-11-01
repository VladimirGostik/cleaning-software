<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NavIconEnum;
use App\Data\ChangePasswordData;
use App\Data\UpdateProfileData;
use App\Models\User;
use App\Navigation\NavItem;
use App\Services\McpTokenService;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly McpTokenService $mcpTokens,
    ) {}

    #[NavItem(label: 'app.profile', route: 'profile.show', icon: NavIconEnum::Profile, group: 'settings', order: 10)]
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        Inertia::encryptHistory();

        return Inertia::render('Profile/Show', [
            'mcpTokens' => $this->mcpTokens->listFor($user),
            'mcpUrl' => url('/mcp'),
        ]);
    }

    public function update(UpdateProfileData $data, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->profileService->updateProfile($user, $data);

        return back()->with('success', __('app.profile_updated'));
    }

    public function changePassword(ChangePasswordData $data, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($data->current_password, $user->password ?? '')) {
            throw ValidationException::withMessages([
                'current_password' => [__('app.invalid_current_password')],
            ]);
        }

        $user->update(['password' => $data->password]);

        return back()->with('success', __('app.password_changed'));
    }
}
