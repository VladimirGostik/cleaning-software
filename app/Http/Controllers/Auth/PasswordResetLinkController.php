<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Data\Auth\ForgotPasswordData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function store(ForgotPasswordData $data): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $data->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return back()->with('flash.success', __($status));
    }
}
