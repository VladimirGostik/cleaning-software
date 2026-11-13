<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Enums\AssistantRoleEnum;
use App\Models\AssistantMessage;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AssistantHistoryMessageData extends Data
{
    /**
     * @param  list<string>  $attachment_names
     * @param  AssistantActionData[]  $actions
     */
    public function __construct(
        public readonly string $id,
        public readonly AssistantRoleEnum $role,
        public readonly string $content,
        #[LiteralTypeScriptType('string[]')]
        public readonly array $attachment_names,
        #[DataCollectionOf(AssistantActionData::class)]
        public readonly array $actions,
        public readonly ?PendingActionData $pending_action,
        public readonly ?string $security_notice,
        public readonly ?string $attachment_note,
        public readonly string $created_at,
    ) {}

    public static function fromModel(AssistantMessage $message): self
    {
        return new self(
            id: $message->id,
            role: $message->role,
            content: $message->content,
            attachment_names: $message->attachment_names ?? [],
            actions: array_map(AssistantActionData::from(...), $message->actions ?? []),
            pending_action: $message->pendingAction === null ? null : PendingActionData::fromModel($message->pendingAction),
            security_notice: $message->security_notice,
            attachment_note: $message->attachment_note,
            created_at: $message->created_at->toIso8601String(),
        );
    }
}
