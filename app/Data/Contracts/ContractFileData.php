<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Models\Media;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractFileData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $file_name,
        public readonly ?string $mime_type,
        public readonly int $size,
        public readonly string $created_at,
        public readonly string $download_url,
        public readonly bool $can_delete,
    ) {}

    public static function fromMedia(Media $media, string $contractId, bool $canDelete): self
    {
        /** @var mixed $original */
        $original = $media->getCustomProperty('original_name');

        return new self(
            id: $media->uuid,
            title: $media->name,
            file_name: is_string($original) && $original !== '' ? $original : $media->file_name,
            mime_type: $media->mime_type,
            size: (int) $media->size,
            created_at: $media->created_at?->toIso8601String() ?? '',
            download_url: route('contracts.files.show', ['contract' => $contractId, 'attachment' => $media->uuid], absolute: false),
            can_delete: $canDelete,
        );
    }
}
