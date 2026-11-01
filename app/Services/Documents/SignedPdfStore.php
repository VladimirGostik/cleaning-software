<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Media;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

final readonly class SignedPdfStore
{
    public const COLLECTION = 'pdf';

    public function store(Document $document, string $pdf, string $filename): Media
    {
        /** @var Media $media */
        $media = $document->addMediaFromString(Crypt::encryptString($pdf))
            ->usingFileName($filename)
            ->toMediaCollection(self::COLLECTION);

        return $media;
    }

    public function read(SpatieMedia $media): string
    {
        $bytes = $this->raw($media);

        return $this->isPlain($bytes) ? $bytes : Crypt::decryptString($bytes);
    }

    public function isEncrypted(SpatieMedia $media): bool
    {
        return ! $this->isPlain($this->raw($media));
    }

    public function encryptInPlace(SpatieMedia $media): void
    {
        $encrypted = Crypt::encryptString($this->raw($media));

        Storage::disk($media->disk)->put($media->getPathRelativeToRoot(), $encrypted);

        $media->forceFill(['size' => strlen($encrypted), 'mime_type' => 'text/plain'])->save();
    }

    private function raw(SpatieMedia $media): string
    {
        return Storage::disk($media->disk)->get($media->getPathRelativeToRoot()) ?? '';
    }

    private function isPlain(string $bytes): bool
    {
        return str_starts_with($bytes, '%PDF');
    }
}
