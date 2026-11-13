<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Models\AssistantConversation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AssistantConversationItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $last_message_at,
        public readonly string $created_at,
    ) {}

    public static function fromModel(AssistantConversation $conversation): self
    {
        return new self(
            id: $conversation->id,
            title: $conversation->title,
            last_message_at: $conversation->last_message_at->toIso8601String(),
            created_at: $conversation->created_at->toIso8601String(),
        );
    }
}
