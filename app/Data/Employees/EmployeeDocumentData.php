<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Enums\DocumentTypeEnum;
use App\Models\Document;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeDocumentData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly DocumentTypeEnum $type,
        public readonly string $file_name,
        public readonly ?string $mime_type,
        public readonly int $size,
        public readonly string $created_at,
        public readonly string $download_url,
    ) {}

    public static function fromModel(Document $document, string $employeeId): self
    {
        $media = $document->getFirstMedia('file');

        return new self(
            id: $document->id,
            title: $document->title,
            type: $document->type,
            file_name: $media->file_name ?? '',
            mime_type: $media->mime_type ?? null,
            size: (int) ($media->size ?? 0),
            created_at: $document->created_at->toIso8601String(),
            download_url: route('employees.documents.download', ['employee' => $employeeId, 'document' => $document->id], absolute: false),
        );
    }
}
