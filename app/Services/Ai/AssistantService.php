<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\Ai\ChatsWithTools;
use App\Data\Ai\AssistantReplyData;
use App\Data\Ai\AssistantRequestData;
use App\Exceptions\AiUnavailableException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final readonly class AssistantService
{
    public function __construct(
        private ChatsWithTools $assistant,
        private AssistantConversationService $conversations,
    ) {}

    public function reply(AssistantRequestData $request, User $user): AssistantReplyData
    {
        $receivedAt = CarbonImmutable::now();
        $lock = Cache::lock('assistant-turn:'.$user->id.':'.$request->client_message_id, config()->integer('ai.assistant.history.lock_seconds'));

        try {
            /** @var AssistantReplyData $reply */
            $reply = $lock->block(config()->integer('ai.assistant.history.lock_wait_seconds'), fn (): AssistantReplyData => $this->turn($request, $user, $receivedAt));

            return $reply;
        } catch (LockTimeoutException) {
            return $this->conversations->replay($user, $request->client_message_id)
                ?? throw ValidationException::withMessages(['status' => [__('app.assistant_turn_in_progress')]]);
        }
    }

    private function turn(AssistantRequestData $request, User $user, CarbonImmutable $receivedAt): AssistantReplyData
    {
        $replayed = $this->conversations->replay($user, $request->client_message_id);

        if ($replayed instanceof AssistantReplyData) {
            return $replayed;
        }

        $conversation = $this->conversations->resolve($user, $request->conversation_id);
        $history = $this->conversations->historyFor($conversation, mb_strlen($request->text()));

        try {
            $reply = $this->assistant->reply($request, $history, $user);
        } catch (AiUnavailableException) {
            throw ValidationException::withMessages(['ai' => [__('app.assistant_unavailable')]]);
        }

        try {
            $conversation = $this->conversations->record($user, $conversation, $request, $reply, $receivedAt);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->conversations->replay($user, $request->client_message_id) ?? throw $exception;
        }

        return $reply->inConversation($conversation);
    }
}
