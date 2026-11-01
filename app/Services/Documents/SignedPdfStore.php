<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Exceptions\SignedPdfUnreadableException;
use App\Models\Document;
use App\Models\Media;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

final readonly class SignedPdfStore
{
    public const COLLECTION = 'pdf';

    private const ENCRYPTED_MIME = 'text/plain';

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

        if ($bytes === '') {
            throw new SignedPdfUnreadableException($media->id);
        }

        if ($this->isPlain($bytes)) {
            return $bytes;
        }

        try {
            return Crypt::decryptString($bytes);
        } catch (DecryptException) {
            throw new SignedPdfUnreadableException($media->id);
        }
    }

    public function isEncrypted(SpatieMedia $media): bool
    {
        return ! $this->isPlain($this->raw($media));
    }

    public function encryptInPlace(SpatieMedia $media): void
    {
        $plain = $this->raw($media);
        $encrypted = Crypt::encryptString($plain);

        if (Crypt::decryptString($encrypted) !== $plain) {
            throw new RuntimeException("Encryption round trip failed (media {$media->id}).");
        }

        Storage::disk($media->disk)->put($media->getPathRelativeToRoot(), $encrypted);

        $this->syncMetadata($media);
    }

    /** Repairs size and mime_type left stale when a run stopped between the file write and the row save. */
    public function syncMetadata(SpatieMedia $media): bool
    {
        $size = strlen($this->raw($media));

        if ($media->size === $size && $media->mime_type === self::ENCRYPTED_MIME) {
            return false;
        }

        $media->forceFill(['size' => $size, 'mime_type' => self::ENCRYPTED_MIME])->save();

        return true;
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
