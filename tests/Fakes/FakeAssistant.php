<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\ChatsWithTools;
use App\Data\Ai\AssistantReplyData;
use App\Data\Ai\AssistantRequestData;
use App\Exceptions\AiUnavailableException;
use App\Models\User;

final class FakeAssistant implements ChatsWithTools
{
    public ?AssistantReplyData $reply = null;

    public bool $unavailable = false;

    /** @var list<AssistantRequestData> */
    public array $requests = [];

    public function reply(AssistantRequestData $request, User $user): AssistantReplyData
    {
        $this->requests[] = $request;

        if ($this->unavailable) {
            throw AiUnavailableException::notConfigured();
        }

        return $this->reply ?? new AssistantReplyData('Fake reply', [], null);
    }
}
