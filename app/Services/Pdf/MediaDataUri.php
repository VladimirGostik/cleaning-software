<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Models\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final readonly class MediaDataUri
{
    public function encode(?Media $media, string $expectedTenantId, string $logPrefix): ?string
    {
        if ($media === null) {
            return null;
        }

        if ($media->tenant_id !== $expectedTenantId) {
            Log::warning($logPrefix.'.tenant_mismatch', [
                'media_id' => $media->id,
                'expected_tenant_id' => $expectedTenantId,
            ]);

            return null;
        }

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        $contents = $disk->exists($path) ? $disk->get($path) : null;

        if ($contents === null) {
            Log::warning($logPrefix.'.file_missing', [
                'media_id' => $media->id,
                'expected_tenant_id' => $expectedTenantId,
            ]);

            return null;
        }

        return 'data:'.$media->mime_type.';base64,'.base64_encode($contents);
    }
}
