<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MediaDetailData extends Data
{
    // Note: id is int (Spatie Media uses auto-increment PK, not UUIDv7)
    public function __construct(
        public readonly int $id,
        public readonly ?string $uuid,
        public readonly string $file_name,
        public readonly string $name,
        public readonly int $size,
        public readonly ?string $mime_type,
        public readonly string $collection_name,
        public readonly string $disk,
        /** @var array<string, mixed> */
        public readonly array $custom_properties,
        public readonly string $model_type,
        public readonly string $model_type_label,
        public readonly ?string $model_id,
        public readonly ?string $model_url,
        public readonly string $url,
        public readonly string $created_at,
    ) {}
}
