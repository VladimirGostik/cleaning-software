<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Contracts\SendsPush;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;

final readonly class FirebasePushSender implements SendsPush
{
    private const int FCM_MULTICAST_LIMIT = 500;

    public function __construct(private Container $container) {}

    public function send(PushMessage $message, array $tokens): array
    {
        if ($tokens === []) {
            return [];
        }

        if (! $this->isConfigured()) {
            Log::info('push.skipped: Firebase credentials are not configured', ['tokens' => count($tokens)]);

            return [];
        }

        $messaging = $this->container->make(Messaging::class);
        $unusable = [];

        foreach (array_chunk($tokens, self::FCM_MULTICAST_LIMIT) as $chunk) {
            $report = $messaging->sendMulticast($message->toFcmPayload(), $chunk);
            $unusable = [...$unusable, ...$this->unusableTokens($report)];
        }

        return array_values(array_unique($unusable));
    }

    /**
     * SenderIdMismatch is deliberately excluded: it signals credentials of the wrong Firebase project, which would wipe every token.
     *
     * @return list<string>
     */
    private function unusableTokens(MulticastSendReport $report): array
    {
        return array_values(array_map(
            static fn (SendReport $item): string => $item->target()->value(),
            array_filter(
                $report->getItems(),
                static fn (SendReport $item): bool => $item->error() instanceof NotFound || $item->messageTargetWasInvalid(),
            ),
        ));
    }

    private function isConfigured(): bool
    {
        $project = config('firebase.default');
        $credentials = is_string($project) ? config("firebase.projects.{$project}.credentials") : null;

        return is_string($credentials) && $credentials !== '';
    }
}
