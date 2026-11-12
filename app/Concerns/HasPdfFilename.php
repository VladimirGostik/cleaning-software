<?php

declare(strict_types=1);

namespace App\Concerns;

/**
 * Safe base filename (no extension) for PDF/attachment downloads. Strips characters
 * that would break a `Content-Disposition` header (quotes, control chars, path
 * separators, `%`) out of the user-influenced document number.
 *
 * @property string|null $number
 */
trait HasPdfFilename
{
    public function pdfFilenameBase(): string
    {
        $safe = preg_replace('/[\x00-\x1F\x7F"\/\\%]/', '', $this->number ?? '');

        return $safe !== null && $safe !== '' ? $safe : $this->pdfFilenameFallback();
    }

    protected function pdfFilenameFallback(): string
    {
        return 'draft';
    }
}
