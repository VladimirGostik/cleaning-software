<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PasswordChanged;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;

#[Tries(3)]
final class AuthEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleLogin(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        activity('auth')
            ->causedBy($user)
            ->withProperties($this->context())
            ->event('login')
            ->log('Používateľ sa prihlásil');
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        /** @var User $user */
        $user = $event->user;

        activity('auth')
            ->causedBy($user)
            ->withProperties($this->context())
            ->event('logout')
            ->log('Používateľ sa odhlásil');
    }

    public function handleFailed(Failed $event): void
    {
        $attemptedEmail = $event->credentials['email'] ?? null;

        activity('auth')
            ->withProperties([...$this->context(), 'attempted_email' => $attemptedEmail])
            ->event('failed')
            ->log('Neúspešný pokus o prihlásenie');
    }

    public function handleLockout(Lockout $event): void
    {
        $attemptedEmail = $event->request->input('email');

        activity('auth')
            ->withProperties([...$this->context(), 'attempted_email' => $attemptedEmail])
            ->event('lockout')
            ->log('Účet dočasne zablokovaný po opakovaných pokusoch');
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        /** @var User $user */
        $user = $event->user;

        activity('auth')
            ->causedBy($user)
            ->withProperties($this->context())
            ->event('password_reset')
            ->log('Heslo bolo obnovené');
    }

    public function handlePasswordChanged(PasswordChanged $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties($this->context())
            ->event('password_changed')
            ->log('Heslo bolo zmenené');
    }

    public function handleRegistered(Registered $event): void
    {
        /** @var User $user */
        $user = $event->user;

        activity('auth')
            ->causedBy($user)
            ->withProperties($this->context())
            ->event('registered')
            ->log('Nový používateľ bol zaregistrovaný');
    }

    public function handleVerified(Verified $event): void
    {
        /** @var User $user */
        $user = $event->user;

        activity('auth')
            ->causedBy($user)
            ->withProperties($this->context())
            ->event('verified')
            ->log('E-mailová adresa bola overená');
    }

    /**
     * @return array{ip: string, user_agent: string|null}
     */
    private function context(): array
    {
        return [
            'ip' => get_client_ip(),
            'user_agent' => request()?->userAgent(),
        ];
    }
}
