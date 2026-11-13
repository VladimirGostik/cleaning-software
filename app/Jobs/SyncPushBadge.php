<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\SendsPush;
use App\Models\User;
use App\Services\DeviceTokenService;
use App\Services\NotificationService;
use App\Services\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Backoff([10, 30, 60])]
#[Timeout(60)]
#[UniqueFor(300)]
final class SyncPushBadge implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public readonly string $userId) {}

    public function uniqueId(): string
    {
        return $this->userId;
    }

    public function handle(NotificationService $notifications, DeviceTokenService $devices, SendsPush $sender): void
    {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User) {
            return;
        }

        $tokens = $devices->iosTokensFor($user);

        if ($tokens === []) {
            return;
        }

        try {
            $unusable = $sender->send(PushMessage::badgeOnly($notifications->badgeCount($user)), $tokens);
        } catch (Throwable $e) {
            Log::error('push.badge.failed', ['user_id' => $this->userId]);

            throw $e;
        }

        $devices->removeTokens($unusable);

        if ($unusable !== []) {
            Log::warning('push.token.pruned', ['user_id' => $this->userId, 'count' => count($unusable)]);
        }
    }
}
