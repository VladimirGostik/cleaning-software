<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationTypeEnum;
use App\Notifications\Channels\TenantDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class BaseTenantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $tenantId)
    {
        $this->afterCommit();
    }

    abstract public function notificationType(): NotificationTypeEnum;

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    abstract protected function title(object $notifiable): string;

    abstract protected function body(object $notifiable): string;

    abstract protected function url(object $notifiable): ?string;

    /**
     * @return array<string, mixed>
     */
    protected function meta(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [TenantDatabaseChannel::class];

        if ($this->mailEnabledFor($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    protected function mailEnabledFor(object $notifiable): bool
    {
        $type = $this->notificationType();
        /** @var array<string, array<string, bool>>|null $prefs */
        $prefs = $notifiable->notification_preferences ?? null;

        return $prefs[$type->value]['mail'] ?? $type->defaultMailEnabled();
    }

    public function databaseType(object $notifiable): string
    {
        return $this->notificationType()->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->notificationType()->value,
            'title' => $this->title($notifiable),
            'body' => $this->body($notifiable),
            'url' => $this->url($notifiable),
            'meta' => $this->meta(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->title($notifiable))
            ->line($this->body($notifiable));

        $url = $this->url($notifiable);
        if ($url !== null) {
            $message->action(__('app.notifications.view'), $url);
        }

        return $message;
    }
}
