<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskAttachmentListData extends Data
{
    public function __construct(
        /** @var TaskAttachmentData[] */
        public readonly array $attachments,
        public readonly bool $can_add,
        public readonly TaskAttachmentConstraintsData $constraints,
    ) {}
}
