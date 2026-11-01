<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class TaskAttachmentFile implements ValidationRule
{
    /** @return array{mime: string, extension: string}|null */
    public static function resolve(UploadedFile $file): ?array
    {
        $detected = (string) $file->getMimeType();
        $clientExtension = strtolower($file->getClientOriginalExtension());

        /** @var array<string, string> $types */
        $types = config('tasks.attachments.types');

        if (isset($types[$detected])) {
            return ['mime' => $detected, 'extension' => $types[$detected]];
        }

        $fallbacks = in_array($detected, self::list('zip_mimes'), true)
            ? config('tasks.attachments.zip_extensions')
            : (in_array($detected, self::list('ole_mimes'), true) ? config('tasks.attachments.ole_extensions') : []);

        if (is_array($fallbacks) && isset($fallbacks[$clientExtension]) && is_string($fallbacks[$clientExtension])) {
            return ['mime' => $fallbacks[$clientExtension], 'extension' => $clientExtension];
        }

        return null;
    }

    /** @return list<string> */
    public static function detectedMimes(): array
    {
        /** @var array<string, string> $types */
        $types = config('tasks.attachments.types');

        return [...array_keys($types), ...self::list('zip_mimes'), ...self::list('ole_mimes')];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || self::resolve($value) === null) {
            $fail(__('app.task_attachment_invalid_type'));
        }
    }

    /** @return list<string> */
    private static function list(string $key): array
    {
        /** @var list<string> */
        return config('tasks.attachments.'.$key, []);
    }
}
