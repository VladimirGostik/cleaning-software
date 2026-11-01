<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class AiUnavailableException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('AI provider is not configured.');
    }

    public static function malformed(): self
    {
        return new self('AI provider returned an unstructured response.');
    }

    public static function failed(Throwable $previous): self
    {
        return new self('AI provider call failed: '.$previous::class, previous: $previous);
    }
}
