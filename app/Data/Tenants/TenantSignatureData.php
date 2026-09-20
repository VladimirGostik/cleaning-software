<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use App\Models\Tenant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TenantSignatureData extends Data
{
    public function __construct(
        public readonly string $url,
        public readonly string $file_name,
        public readonly string $mime_type,
        public readonly int $size,
        public readonly string $uploaded_at,
    ) {}

    public static function fromTenant(Tenant $tenant): ?self
    {
        $media = $tenant->signatureMedia;

        if ($media === null) {
            return null;
        }

        return new self(
            url: route('settings.invoicing.signature'),
            file_name: $media->file_name,
            mime_type: $media->mime_type ?? 'application/octet-stream',
            size: $media->size,
            uploaded_at: $media->created_at?->toIso8601String() ?? '',
        );
    }
}
