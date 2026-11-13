<?php

declare(strict_types=1);

namespace App\Data\Schedule;

use App\Models\Media;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class JobPhotoData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $mime_type,
        public readonly int $size,
    ) {}

    public static function fromMedia(Media $media): self
    {
        return new self(
            id: $media->uuid,
            name: $media->name,
            mime_type: (string) $media->mime_type,
            size: (int) $media->size,
        );
    }
}
