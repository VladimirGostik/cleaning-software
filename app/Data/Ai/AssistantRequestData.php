<?php

declare(strict_types=1);

namespace App\Data\Ai;

use App\Rules\AllowedUploadFile;
use App\Services\Pdf\PdfPageCounter;
use Closure;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class AssistantRequestData extends Data
{
    /** @param  list<UploadedFile>  $attachments */
    public function __construct(
        public readonly string $company_id,
        public readonly ?string $conversation_id,
        public readonly string $client_message_id,
        #[LiteralTypeScriptType('string')]
        public readonly ?string $content = null,
        #[LiteralTypeScriptType('File[]')]
        public readonly array $attachments = [],
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        $properties['conversation_id'] ??= null;

        if (is_array($properties['attachments'] ?? null)) {
            $properties['attachments'] = array_values($properties['attachments']);
        }

        return $properties;
    }

    /** @return array<string, mixed> */
    public static function rules(ValidationContext $context): array
    {
        $maxChars = config()->integer('ai.assistant.max_message_chars', 4000);
        $maxFiles = config()->integer('ai.assistant.attachments.max_files');
        $maxName = config()->integer('ai.assistant.attachments.max_name_chars');
        $attachments = data_get($context->payload, 'attachments');
        $hasFiles = is_array($attachments) && $attachments !== [];
        $tooMany = is_array($attachments) && count($attachments) > $maxFiles;

        return [
            'company_id' => ['required', 'uuid'],
            'conversation_id' => ['nullable', 'uuid'],
            'client_message_id' => ['required', 'uuid'],
            'content' => [$hasFiles ? 'nullable' : 'required', 'string', 'max:'.$maxChars],
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

    public function text(): string
    {
        return $this->content ?? '';
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['content.required' => __('app.assistant_message_empty')];
    }

    private static function isPdf(UploadedFile $file): bool
    {
        $resolved = AllowedUploadFile::resolve($file, 'ai.assistant.attachments');

        return $resolved !== null && $resolved['mime'] === 'application/pdf';
    }
}
