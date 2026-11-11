<?php

declare(strict_types=1);

namespace App\Data\Tasks;

use App\Rules\AllowedUploadFile;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class TaskAttachmentUploadData extends Data
{
    public function __construct(
        #[LiteralTypeScriptType('File')]
        public readonly UploadedFile $file,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config()->integer('tasks.attachments.max_size_kb'), new AllowedUploadFile],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'file.max' => __('app.task_attachment_too_large', ['max' => (int) (config()->integer('tasks.attachments.max_size_kb') / 1024)]),
            'file.uploaded' => __('app.task_attachment_too_large', ['max' => (int) (config()->integer('tasks.attachments.max_size_kb') / 1024)]),
        ];
    }
}
