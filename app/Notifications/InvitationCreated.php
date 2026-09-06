<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Tries;

#[Tries(3)]
final class InvitationCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $tenantName,
        public readonly string $roleName,
    ) {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('app.notifications_invitation_subject', ['tenant' => $this->tenantName]))
            ->line(__('app.notifications_invitation_body', [
                'tenant' => $this->tenantName,
                'role' => $this->roleName,
            ]))
            ->action(__('app.notifications_invitation_action'), route('invitations.show', $this->token));
    }
}
