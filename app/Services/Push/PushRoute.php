<?php

declare(strict_types=1);

namespace App\Services\Push;

final class PushRoute
{
    public const string FALLBACK = '/notifications';

    private const string PATTERN = '#^/(?:(?:tasks|invoices|quotes|expenses)/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|pending-actions)$#i';

    /** Only paths the mobile router knows; anything else (web-only pages) opens the notification list. */
    public static function fromUrl(mixed $url): string
    {
        $path = is_string($url) ? parse_url($url, PHP_URL_PATH) : null;

        return is_string($path) && preg_match(self::PATTERN, $path) === 1 ? $path : self::FALLBACK;
    }
}
