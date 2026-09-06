<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MediaFileData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $file_name,
        public readonly ?string $mime_type,
        public readonly int $size,
        public readonly string $download_url,
    ) {}

    public static function fromMedia(Media $media, string $downloadUrl): self
    {
        return new self(
            uuid: (string) $media->uuid,
            file_name: $media->file_name,
            mime_type: $media->mime_type,
            size: $media->size,
            download_url: $downloadUrl,
        );
    }
}
