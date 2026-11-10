<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TokenPaymentPreviewData extends Data
{
    /** @param  list<UploadedFile>  $files */
    public function __construct(
        public readonly string $company_id,
        #[LiteralTypeScriptType('File[]')]
        public readonly array $files,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'company_id' => ['required', 'uuid'],
            ...PaymentImportUploadData::rules(),
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return PaymentImportUploadData::messages();
    }
}
