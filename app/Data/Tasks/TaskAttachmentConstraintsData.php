<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskAttachmentConstraintsData extends Data
{
    public function __construct(
        public readonly int $max_size_kb,
        public readonly int $max_count,
        /** @var string[] */
        public readonly array $allowed_mimes,
        /** @var string[] */
        public readonly array $allowed_extensions,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array<string, string> $types */
        $types = config('tasks.attachments.types');
        /** @var list<string> $extensions */
        $extensions = config('tasks.attachments.extensions');

        return new self(
            max_size_kb: config()->integer('tasks.attachments.max_size_kb'),
            max_count: config()->integer('tasks.attachments.max_count'),
            allowed_mimes: array_keys($types),
            allowed_extensions: $extensions,
        );
    }
}
