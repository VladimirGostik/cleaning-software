<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Enums\AssistantRoleEnum;
use App\Rules\AllowedUploadFile;
use App\Services\Pdf\PdfPageCounter;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class AssistantRequestData extends Data
{
    /**
     * @param  AssistantMessageData[]  $messages
     * @param  list<UploadedFile>  $attachments
     */
    public function __construct(
        public readonly string $company_id,
        #[DataCollectionOf(AssistantMessageData::class)]
        public readonly array $messages,
        #[LiteralTypeScriptType('File[]')]
        public readonly array $attachments = [],
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (is_array($properties['attachments'] ?? null)) {
            $properties['attachments'] = array_values($properties['attachments']);
        }

        return $properties;
    }

    /** @return array<string, mixed> */
    public static function rules(ValidationContext $context): array
    {
        $maxMessages = config()->integer('ai.assistant.max_messages', 20);
        $maxChars = config()->integer('ai.assistant.max_message_chars', 4000);
        $maxTotal = config()->integer('ai.assistant.max_total_chars', 12000);
        $maxFiles = config()->integer('ai.assistant.attachments.max_files');
        $maxName = config()->integer('ai.assistant.attachments.max_name_chars');
        $attachments = data_get($context->payload, 'attachments');
        $hasFiles = is_array($attachments) && $attachments !== [];
        $tooMany = is_array($attachments) && count($attachments) > $maxFiles;

        return [
            'company_id' => ['required', 'uuid'],
            'messages' => [
                'required', 'array', 'min:1', 'max:'.$maxMessages,
                function (string $attribute, mixed $value, Closure $fail) use ($maxTotal, $hasFiles): void {
                    $messages = is_array($value) ? array_values($value) : [];
                    $lastIndex = count($messages) - 1;
                    $last = $messages[$lastIndex] ?? null;
                    $total = array_sum(array_map(static fn (mixed $m): int => is_array($m) && is_string($m['content'] ?? null) ? mb_strlen($m['content']) : 0, $messages));

                    if (! is_array($last) || ($last['role'] ?? null) !== AssistantRoleEnum::User->value) {
                        $fail(__('app.assistant_last_must_be_user'));
                    }

                    if ($total > $maxTotal) {
                        $fail(__('app.assistant_too_long'));
                    }

                    foreach ($messages as $index => $message) {
                        $empty = ! is_array($message) || ! is_string($message['content'] ?? null) || trim($message['content']) === '';
                        $hasNames = is_array($message) && ($message['role'] ?? null) === AssistantRoleEnum::User->value && is_array($message['attachment_names'] ?? null) && $message['attachment_names'] !== [];

                        if ($empty && ! $hasNames && ! ($hasFiles && $index === $lastIndex)) {
                            $fail(__('app.assistant_message_empty'));

                            break;
                        }
                    }
                },
            ],
            'messages.*.role' => ['required', Rule::enum(AssistantRoleEnum::class)],
            'messages.*.content' => ['nullable', 'string', 'max:'.$maxChars],
            'messages.*.attachment_names' => ['sometimes', 'array', 'max:'.$maxFiles],
            'messages.*.attachment_names.*' => ['string', 'max:'.$maxName],
            'attachments' => [
                'sometimes', 'array', 'max:'.$maxFiles,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_array($value) && count($value) > config()->integer('ai.assistant.attachments.max_files')) {
                        return;
                    }

                    $files = array_filter(is_array($value) ? $value : [], static fn (mixed $file): bool => $file instanceof UploadedFile);

                    if (array_sum(array_map(static fn (UploadedFile $file): int => (int) $file->getSize(), $files)) > config()->integer('ai.assistant.attachments.total_max_kb') * 1024) {
                        $fail(__('app.assistant_attachment_total_too_large', ['max' => intdiv(config()->integer('ai.assistant.attachments.total_max_kb'), 1024)]));
                    }

                    $pages = 0;

                    foreach ($files as $file) {
                        if (self::isPdf($file)) {
                            $pages += app(PdfPageCounter::class)->count((string) $file->getRealPath()) ?? 0;
                        }
                    }

                    if ($pages > config()->integer('ai.assistant.attachments.total_max_pages')) {
                        $fail(__('app.assistant_attachments_total_pages', ['max' => config()->integer('ai.assistant.attachments.total_max_pages')]));
                    }
                },
            ],
            'attachments.*' => [
                'file',
                new AllowedUploadFile('ai.assistant.attachments', 'app.assistant_attachment_invalid_type'),
                function (string $attribute, mixed $value, Closure $fail) use ($tooMany, $maxName): void {
                    if ($tooMany || ! $value instanceof UploadedFile) {
                        return;
                    }

                    if (mb_strlen($value->getClientOriginalName()) > $maxName) {
                        $fail(__('app.assistant_attachment_name_too_long', ['max' => $maxName]));

                        return;
                    }

                    if (AllowedUploadFile::resolve($value, 'ai.assistant.attachments') === null) {
                        return;
                    }

                    $pdf = self::isPdf($value);
                    $maxKb = config()->integer($pdf ? 'ai.assistant.attachments.pdf_max_kb' : 'ai.assistant.attachments.image_max_kb');

                    if ((int) $value->getSize() > $maxKb * 1024) {
                        $fail(__('app.assistant_attachment_too_large', ['max' => intdiv($maxKb, 1024)]));

                        return;
                    }

                    if (! $pdf) {
                        return;
                    }

                    $pages = app(PdfPageCounter::class)->count((string) $value->getRealPath());

                    if ($pages === null) {
                        $fail(__('app.assistant_attachment_unreadable'));
                    } elseif ($pages > config()->integer('ai.assistant.attachments.pdf_max_pages')) {
                        $fail(__('app.assistant_attachment_too_many_pages', ['max' => config()->integer('ai.assistant.attachments.pdf_max_pages')]));
                    }
                },
            ],
        ];
    }

    private static function isPdf(UploadedFile $file): bool
    {
        $resolved = AllowedUploadFile::resolve($file, 'ai.assistant.attachments');

        return $resolved !== null && $resolved['mime'] === 'application/pdf';
    }
}
