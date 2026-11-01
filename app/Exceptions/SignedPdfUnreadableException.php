<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class SignedPdfUnreadableException extends RuntimeException
{
    public function __construct(public readonly int|string $mediaId)
    {
        parent::__construct("Signed PDF snapshot is unreadable (media {$mediaId}).");
    }
}
