<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Models\TaskComment;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskCommentStoreData extends Data
{
    public function __construct(
        #[Required, Max(TaskComment::MAX_BODY_CHARS)]
        public readonly string $body,
        #[Nullable, Uuid]
        public readonly ?string $answers_comment_id = null,
    ) {}
}
