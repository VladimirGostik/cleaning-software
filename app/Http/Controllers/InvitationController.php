<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Invitations\AcceptInvitationData;
use App\Data\Invitations\InvitationAcceptPageData;
use App\Enums\InvitationAcceptStateEnum;
use App\Models\User;
use App\Services\InvitationAcceptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guest-accessible by design — the invitation token is the credential. No
 * `#[Authorize]`; the POST action is rate-limited via `throttle:invitation-accept`.
 */
final class InvitationController extends Controller
{
    public function show(string $token, InvitationAcceptService $service): Response|RedirectResponse
    {
        $invitation = $service->resolve($token);

        if (! $invitation->isAcceptable()) {
            return Inertia::render('Invitations/Accept', [
                'invitation' => new InvitationAcceptPageData(
                    state: InvitationAcceptStateEnum::Expired,
                    token: $token,
                    email: null,
                    tenant_name: null,
                    role_name: null,
                    invited_email: null,
                ),
            ]);
        }

        if (Auth::check()) {
            /** @var User $loggedIn */
            $loggedIn = Auth::user();

            if (strtolower($loggedIn->email) === strtolower($invitation->email)) {
                $user = $service->accept($invitation, new AcceptInvitationData(password: ''), skipPasswordCheck: true);
                Auth::login($user);
                Session::put('active_tenant_id', $invitation->tenant_id);
                Session::regenerate();

                return to_route('dashboard')->with('success', __('app.invitation_accepted_success'));
            }

            return Inertia::render('Invitations/Accept', [
                'invitation' => new InvitationAcceptPageData(
                    state: InvitationAcceptStateEnum::WrongUser,
                    token: $token,
                    email: null,
                    tenant_name: null,
                    role_name: null,
                    invited_email: $invitation->email,
                ),
            ]);
        }

        $state = User::where('email', $invitation->email)->exists()
            ? InvitationAcceptStateEnum::ExistingUser
            : InvitationAcceptStateEnum::NewUser;

        return Inertia::render('Invitations/Accept', [
            'invitation' => new InvitationAcceptPageData(
                state: $state,
                token: $token,
                email: $invitation->email,
                tenant_name: $invitation->tenant?->name,
                role_name: $invitation->role_name,
                invited_email: null,
            ),
        ]);
    }

    public function accept(string $token, AcceptInvitationData $data, InvitationAcceptService $service): RedirectResponse
    {
        $invitation = $service->resolve($token);
        $user = $service->accept($invitation, $data);

        Auth::login($user);
        Session::put('active_tenant_id', $invitation->tenant_id);
        Session::regenerate();

        return to_route('dashboard')->with('success', __('app.invitation_accepted_success'));
    }
}
