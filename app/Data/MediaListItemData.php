<?php

declare(strict_types=1);

namespace App\Data;

use App\Services\MediaUrlResolver;
use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MediaListItemData extends Data
{
    // Note: id is int (Spatie Media uses auto-increment PK, not UUIDv7)
    public function __construct(
        public readonly int $id,
        public readonly ?string $uuid,
        public readonly string $file_name,
        public readonly string $name,
        public readonly ?string $mime_type,
        public readonly int $size,
        public readonly string $collection_name,
        public readonly string $disk,
        public readonly string $model_type_label,
        public readonly ?string $model_id,
        public readonly ?string $model_url,
        public readonly string $url,
        public readonly string $created_at,
    ) {}

    public static function fromModel(Media $media): self
    {
        $resolved = app(MediaUrlResolver::class)->resolve($media->model_type, $media->model_id);

        return new self(
            id: (int) $media->id,
            uuid: $media->uuid,
            file_name: $media->file_name,
            name: $media->name,
            mime_type: $media->mime_type,
            size: (int) $media->size,
            collection_name: $media->collection_name,
            disk: $media->disk,
            model_type_label: $resolved['label'],
            model_id: $media->model_id !== null ? (string) $media->model_id : null,
            model_url: $resolved['url'],
            url: $media->getFullUrl(),
            created_at: $media->created_at?->toIso8601String() ?? '',
        );
    }
}
