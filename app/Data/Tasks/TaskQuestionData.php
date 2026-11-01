<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskQuestionData extends Data
{
    public function __construct(
        public readonly string $comment_id,
        public readonly bool $answered,
        public readonly bool $can_answer,
    ) {}
}
