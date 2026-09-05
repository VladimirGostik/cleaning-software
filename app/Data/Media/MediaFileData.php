<?php

declare(strict_types=1);

namespace App\Data\Media;

use Spatie\LaravelData\Data;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MediaFileData extends Data
{
    public function __construct(
        public string $uuid,
        public string $file_name,
        public ?string $mime_type,
        public int $size,
        public string $download_url,
    ) {}

    public static function fromMedia(Media $media, string $downloadUrl): self
    {
        return new self(
            uuid: $media->uuid ?? (string) $media->id,
            file_name: $media->file_name,
            mime_type: $media->mime_type,
            size: $media->size,
            download_url: $downloadUrl,
        );
    }
}
