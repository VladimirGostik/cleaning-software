<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PasswordChanged;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class LogAuthenticationActivity
{
    public function handleLogin(Login $event): void
    {
        $this->log('login', $event->user, request()->input('email'));
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof Model) {
            $this->log('logout', $event->user, $event->user->getAttribute('email'));
        }
    }

    public function handleFailed(Failed $event): void
    {
        $this->log('login_failed', null, $event->credentials['email'] ?? null);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->log('password_reset', $event->user, $event->user->getAttribute('email'));
    }

    public function handlePasswordResetLinkSent(PasswordResetLinkSent $event): void
    {
        $this->log('password_reset_link_sent', $event->user, $event->user->getAttribute('email'));
    }

    public function handleLockout(Lockout $event): void
    {
        $email = $event->request->input('email');
        $email = is_string($email) ? Str::limit($email, 255, '') : null;

        $ip = get_client_ip();

        if (! Cache::add('login-lockout-logged:ip:'.$ip, true, 60)
            || ! Cache::add('login-lockout-logged:'.sha1(Str::lower((string) $email).'|'.$ip), true, 60)) {
            return;
        }

        $this->log('login_lockout', null, $email);
    }

    public function handlePasswordChanged(PasswordChanged $event): void
    {
        $this->log('password_changed', $event->user, $event->user->email);
    }

    private function log(string $description, mixed $causer, mixed $email): void
    {
        activity()
            ->causedBy($causer instanceof Model ? $causer : null)
            ->withProperties([
                'ip' => get_client_ip(),
                'user_agent' => request()->userAgent(),
                'email' => is_string($email) ? $email : null,
            ])
            ->log($description);
    }
}
