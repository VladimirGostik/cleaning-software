<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Invitations\AcceptInvitationData;
use App\Models\User;
use App\Services\InvitationAcceptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Inertia\Response;

final class InvitationController extends Controller
{
    public function show(string $token, InvitationAcceptService $service): Response|RedirectResponse
    {
        $invitation = $service->resolve($token);

        if (! $invitation->isAcceptable()) {
            return Inertia::render('Invitations/Accept', [
                'state' => 'expired',
                'token' => $token,
                'email' => null,
                'tenantName' => null,
                'roleName' => null,
                'invitedEmail' => null,
            ]);
        }

        if (Auth::check()) {
            /** @var User $loggedIn */
            $loggedIn = Auth::user();

            if (strtolower($loggedIn->email) === strtolower($invitation->email)) {
                // Same-email — skip password, attach membership directly.
                $user = $service->accept($invitation, new AcceptInvitationData(password: ''), skipPasswordCheck: true);
                Auth::login($user);
                Session::put('active_tenant_id', $invitation->tenant_id);
                Session::regenerate();

                return to_route('dashboard')->with('flash.success', __('app.invitation.accepted_success'));
            }

            return Inertia::render('Invitations/Accept', [
                'state' => 'wrong_user',
                'token' => $token,
                'email' => null,
                'tenantName' => null,
                'roleName' => null,
                'invitedEmail' => $invitation->email,
            ]);
        }

        $state = User::where('email', $invitation->email)->exists()
            ? 'existing_user'
            : 'new_user';

        return Inertia::render('Invitations/Accept', [
            'state' => $state,
            'token' => $token,
            'email' => $invitation->email,
            'tenantName' => $invitation->tenant?->name,
            'roleName' => $invitation->role_name,
            'invitedEmail' => null,
        ]);
    }

    public function accept(string $token, AcceptInvitationData $data, InvitationAcceptService $service): RedirectResponse
    {
        $invitation = $service->resolve($token);
        $user = $service->accept($invitation, $data);

        Auth::login($user);
        Session::put('active_tenant_id', $invitation->tenant_id);
        Session::regenerate();

        return to_route('dashboard')->with('flash.success', __('app.invitation.accepted_success'));
    }
}
