<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Enums\TaskCommentAuthorEnum;
use App\Models\TaskComment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskCommentData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly TaskCommentAuthorEnum $author_kind,
        public readonly string $author_name,
        public readonly string $body,
        public readonly string $created_at,
        public readonly bool $is_mine,
        public readonly bool $can_edit,
        public readonly bool $can_delete,
        public readonly ?string $edited_at,
        public readonly ?string $answers_comment_id,
    ) {}

    /** Expects `author` loaded; `$answersCommentId` is null when the viewer cannot see the question. */
    public static function fromModel(TaskComment $comment, string $viewerId, bool $canEdit, bool $canDelete, ?string $answersCommentId): self
    {
        return new self(
            id: $comment->id,
            author_kind: $comment->author_kind,
            author_name: $comment->author->name,
            body: $comment->body,
            created_at: $comment->created_at->toIso8601String(),
            is_mine: ! $comment->isByAi() && $comment->user_id === $viewerId,
            can_edit: $canEdit,
            can_delete: $canDelete,
            edited_at: $comment->edited_at?->toIso8601String(),
            answers_comment_id: $answersCommentId,
        );
    }
}
