<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use App\Rules\AllowedUploadFile;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class TokenContractFileData extends Data
{
    public function __construct(
        public readonly string $company_id,
        #[LiteralTypeScriptType('File')]
        public readonly UploadedFile $file,
        public readonly ?string $contract_id,
        public readonly ?string $title = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid'],
            'contract_id' => [request()->routeIs('token.contracts.attachments.store') ? 'required' : 'nullable', 'uuid'],
            'title' => ['nullable', 'string', 'max:'.(request()->routeIs('token.contracts.attachments.store') ? 120 : 255)],
            'file' => [
                'required',
                'file',
                'max:'.config()->integer('documents.contract_files.max_size_kb'),
                new AllowedUploadFile('documents.contract_files', 'app.contract_file_invalid_type'),
            ],
        ];
    }
}
