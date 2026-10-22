<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class PdfMergeFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $exitCode = 0,
        public readonly string $stderr = '',
    ) {
        parent::__construct($message);
    }
}
