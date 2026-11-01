<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Enums\DocumentTypeEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class EmployeeDocumentUploadData extends Data
{
    public function __construct(
        #[Required]
        public readonly DocumentTypeEnum $type,
        #[LiteralTypeScriptType('File')]
        public readonly UploadedFile $file,
        #[Nullable, Max(255)]
        public readonly ?string $title = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $constraints = EmployeeDocumentConstraintsData::fromConfig();

        return [
            'type' => ['required', Rule::enum(DocumentTypeEnum::class)->only(DocumentTypeEnum::employeeFiles())],
            'file' => ['required', 'file', 'mimetypes:'.implode(',', $constraints->allowed_mimes), 'max:'.$constraints->max_size_kb],
        ];
    }
}
