<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Models\AssistantConversation;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AssistantReplyData extends Data
{
    public function __construct(
        public readonly string $reply,
        /** @var AssistantActionData[] */
        #[DataCollectionOf(AssistantActionData::class)]
        public readonly array $actions,
        public readonly ?PendingActionData $pending_action,
        public readonly ?string $security_notice = null,
        public readonly ?string $attachment_note = null,
        #[LiteralTypeScriptType('string')]
        public readonly ?string $conversation_id = null,
        #[LiteralTypeScriptType('string')]
        public readonly ?string $conversation_title = null,
    ) {}

    public function inConversation(AssistantConversation $conversation): self
    {
        return new self(
            reply: $this->reply,
            actions: $this->actions,
            pending_action: $this->pending_action,
            security_notice: $this->security_notice,
            attachment_note: $this->attachment_note,
            conversation_id: $conversation->id,
            conversation_title: $conversation->title,
        );
    }
}
