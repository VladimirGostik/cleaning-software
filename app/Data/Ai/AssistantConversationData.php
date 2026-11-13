<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Models\AssistantConversation;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AssistantConversationData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $last_message_at,
        /** @var AssistantHistoryMessageData[] */
        #[DataCollectionOf(AssistantHistoryMessageData::class)]
        public readonly array $messages,
    ) {}

    public static function fromModel(AssistantConversation $conversation): self
    {
        return new self(
            id: $conversation->id,
            title: $conversation->title,
            last_message_at: $conversation->last_message_at->toIso8601String(),
            messages: $conversation->messages->map(AssistantHistoryMessageData::fromModel(...))->all(),
        );
    }
}
