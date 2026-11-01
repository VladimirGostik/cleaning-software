<?php

declare(strict_types=1);

namespace App\Data\Ai;

use JsonSerializable;

/** MCP-only: carries the one-time token, so it is deliberately not a Spatie Data and never reaches the generated mobile types. */
final readonly class PendingActionPreparedData implements JsonSerializable
{
    public function __construct(
        public PendingActionData $pending_action,
        public ?string $token,
    ) {}

    /** @return array{pending_action: PendingActionData, token: string|null} */
    public function jsonSerialize(): array
    {
        return ['pending_action' => $this->pending_action, 'token' => $this->token];
    }
}
