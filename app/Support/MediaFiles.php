<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Media;
use App\Services\Ai\Guard\PromptGuard;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaFiles
{
    private const int MAX_NAME_LENGTH = 120;

    public static function cleanName(string $original): string
    {
        $name = str_replace('\\', '/', $original);
        $slash = strrpos($name, '/');
        $name = $slash === false ? $name : substr($name, $slash + 1);

        $name = PromptGuard::stripInvisible($name);
        $name = (string) preg_replace('/\p{Cc}/u', '', $name);
        $name = self::trimSeparators($name);

        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $dot = mb_strrpos($name, '.');
            $extensionLength = $dot === false ? 0 : mb_strlen($name) - $dot;

            $name = $dot !== false && $extensionLength >= 2 && $extensionLength <= 11
                ? mb_substr($name, 0, self::MAX_NAME_LENGTH - $extensionLength).mb_substr($name, $dot)
                : mb_substr($name, 0, self::MAX_NAME_LENGTH);

            $name = self::trimSeparators($name);
        }

        return $name === '' ? 'file' : $name;
    }

    /** @param list<string> $inlineMimes */
    public static function stream(Media $media, string $downloadName, array $inlineMimes): StreamedResponse
    {
        $mime = (string) $media->mime_type;
        $fallback = trim((string) preg_replace('/[^\x20-\x7E]|[%\/\\\\]/', '_', Str::ascii($downloadName)));

        return response()->stream(
            function () use ($media): void {
                /** @var resource $stream */
                $stream = $media->stream();
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => $mime,
                'Content-Length' => (string) $media->size,
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    in_array($mime, $inlineMimes, true) ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                    $downloadName,
                    $fallback === '' ? 'file' : $fallback,
                ),
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private static function trimSeparators(string $value): string
    {
        return (string) preg_replace('/^\p{Z}+|\p{Z}+$/u', '', $value);
    }
}
