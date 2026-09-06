<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Media;
use App\Models\TemporaryUpload;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Mime + size guard for a staged temporary upload, applied on top of
 * `OwnedTemporaryMedia` (which verifies ownership/tenant). Reads the
 * content-sniffed `mime_type` Spatie recorded at upload time — never the
 * client-supplied `Content-Type` header.
 */
final class TemporaryMediaConstraints implements ValidationRule
{
    /**
     * @param  list<string>  $allowedMimes
     */
    public function __construct(
        private readonly array $allowedMimes,
        private readonly int $maxSizeKb,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $media = Media::query()
            ->where('uuid', $value)
            ->where('model_type', (new TemporaryUpload)->getMorphClass())
            ->first();

        if ($media === null) {
            // Ownership rule (`OwnedTemporaryMedia`) already fails on a missing/foreign upload.
            return;
        }

        if (! in_array($media->mime_type, $this->allowedMimes, true)) {
            $fail(__('app.quote_document_invalid_type'));

            return;
        }

        if ($media->size > $this->maxSizeKb * 1024) {
            $fail(__('app.quote_document_too_large'));
        }
    }
}
