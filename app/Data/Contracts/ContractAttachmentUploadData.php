<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Rules\AllowedUploadFile;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class ContractAttachmentUploadData extends Data
{
    public function __construct(
        #[LiteralTypeScriptType('File')]
        public readonly UploadedFile $file,
        #[Nullable, Max(120)]
        public readonly ?string $title = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.config()->integer('documents.contract_files.max_size_kb'),
                new AllowedUploadFile('documents.contract_files', 'app.contract_file_invalid_type'),
            ],
        ];
    }
}
