<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PasswordResetLinkData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

final class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(PasswordResetLinkData $data): RedirectResponse
    {
        Password::sendResetLink(['email' => $data->email]);

        return back()->with('status', __('passwords.sent'));
    }
}
