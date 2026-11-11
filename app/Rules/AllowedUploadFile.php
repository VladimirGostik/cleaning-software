<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class AllowedUploadFile implements ValidationRule
{
    public function __construct(
        private readonly string $config = 'tasks.attachments',
        private readonly string $messageKey = 'app.task_attachment_invalid_type',
    ) {}

    /** @return array{mime: string, extension: string}|null */
    public static function resolve(UploadedFile $file, string $config = 'tasks.attachments'): ?array
    {
        return self::resolveDetected((string) $file->getMimeType(), strtolower($file->getClientOriginalExtension()), $config);
    }

    /** @return array{mime: string, extension: string}|null */
    public static function resolveDetected(string $mime, string $extension, string $config = 'tasks.attachments'): ?array
    {
        $extension = strtolower($extension);

        /** @var array<string, string> $types */
        $types = config($config.'.types');

        if (isset($types[$mime])) {
            return ['mime' => $mime, 'extension' => $types[$mime]];
        }

        $fallbacks = in_array($mime, self::list($config, 'zip_mimes'), true)
            ? config($config.'.zip_extensions')
            : (in_array($mime, self::list($config, 'ole_mimes'), true) ? config($config.'.ole_extensions') : []);

        if (is_array($fallbacks) && isset($fallbacks[$extension]) && is_string($fallbacks[$extension])) {
            return ['mime' => $fallbacks[$extension], 'extension' => $extension];
        }

        return null;
    }

    /** @return list<string> */
    public static function detectedMimes(string $config = 'tasks.attachments'): array
    {
        /** @var array<string, string> $types */
        $types = config($config.'.types');

        return [...array_keys($types), ...self::list($config, 'zip_mimes'), ...self::list($config, 'ole_mimes')];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || self::resolve($value, $this->config) === null) {
            $fail(__($this->messageKey));
        }
    }

    /** @return list<string> */
    private static function list(string $config, string $key): array
    {
        /** @var list<string> */
        return config($config.'.'.$key, []);
    }
}
