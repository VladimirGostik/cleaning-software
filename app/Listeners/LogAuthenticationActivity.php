<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

final class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        activity()
            ->causedBy($event->user)
            ->withProperties(['ip' => get_client_ip(), 'user_agent' => request()->userAgent()])
            ->log('login');
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            activity()
                ->causedBy($event->user)
                ->withProperties(['ip' => get_client_ip()])
                ->log('logout');
        }
    }

    public function handleFailed(Failed $event): void
    {
        activity()
            ->withProperties([
                'ip' => get_client_ip(),
                'email' => $event->credentials['email'] ?? null,
            ])
            ->log('login_failed');
    }
}
