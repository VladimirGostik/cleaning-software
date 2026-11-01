<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Models\Media;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TaskAttachmentData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $mime_type,
        public readonly int $size,
        public readonly bool $previewable,
        public readonly ?string $uploaded_by_name,
        public readonly string $created_at,
        public readonly string $download_url,
        public readonly bool $can_delete,
    ) {}

    public static function fromMedia(Media $media, string $downloadUrl, bool $canDelete): self
    {
        /** @var list<string> $previewable */
        $previewable = config('tasks.attachments.previewable_mimes');
        $uploader = $media->getCustomProperty('uploaded_by_name');

        return new self(
            id: $media->uuid,
            name: $media->name,
            mime_type: (string) $media->mime_type,
            size: (int) $media->size,
            previewable: in_array($media->mime_type, $previewable, true),
            uploaded_by_name: is_string($uploader) ? $uploader : null,
            created_at: $media->created_at?->toIso8601String() ?? '',
            download_url: $downloadUrl,
            can_delete: $canDelete,
        );
    }
}
