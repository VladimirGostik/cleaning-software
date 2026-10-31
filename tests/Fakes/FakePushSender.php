<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\SendsPush;
use App\Services\Push\PushMessage;
use RuntimeException;

final class FakePushSender implements SendsPush
{
    /** @var list<array{message: PushMessage, tokens: list<string>}> */
    public array $sent = [];

    /** @var list<string> */
    public array $unusable = [];

    public bool $throws = false;

    public function send(PushMessage $message, array $tokens): array
    {
        if ($this->throws) {
            throw new RuntimeException('fcm down');
        }

        $this->sent[] = ['message' => $message, 'tokens' => $tokens];

        return array_values(array_intersect($tokens, $this->unusable));
    }

    /** @return list<string> */
    public function allTokens(): array
    {
        return array_merge(...array_map(static fn (array $send): array => $send['tokens'], $this->sent));
    }
}
