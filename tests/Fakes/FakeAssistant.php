<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Contracts\Ai\ChatsWithTools;
use App\Data\Ai\AssistantMessageData;
use App\Data\Ai\AssistantReplyData;
use App\Data\Ai\AssistantRequestData;
use App\Exceptions\AiUnavailableException;
use App\Models\User;
use Closure;

final class FakeAssistant implements ChatsWithTools
{
    public ?AssistantReplyData $reply = null;

    public bool $unavailable = false;

    public ?Closure $onReply = null;

    /** @var list<AssistantRequestData> */
    public array $requests = [];

    /** @var list<list<AssistantMessageData>> */
    public array $histories = [];

    public function reply(AssistantRequestData $request, array $history, User $user): AssistantReplyData
    {
        $this->requests[] = $request;
        $this->histories[] = $history;

        if ($this->onReply instanceof Closure) {
            ($this->onReply)();
        }

        if ($this->unavailable) {
            throw AiUnavailableException::notConfigured();
        }

        return $this->reply ?? new AssistantReplyData('Fake reply', [], null);
    }
}
