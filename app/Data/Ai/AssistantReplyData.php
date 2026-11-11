<?php

declare(strict_types=1);

namespace App\Data\Ai;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
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
    ) {}
}
