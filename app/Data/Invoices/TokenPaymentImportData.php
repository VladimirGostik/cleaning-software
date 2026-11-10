<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TokenPaymentImportData extends Data
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $exclude_references
     */
    public function __construct(
        public readonly string $company_id,
        #[LiteralTypeScriptType('File[]')]
        public readonly array $files,
        public readonly array $exclude_references = [],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            ...TokenPaymentPreviewData::rules(),
            'exclude_references' => ['nullable', 'array', 'max:'.config()->integer('invoicing.payment_import.max_rows')],
            'exclude_references.*' => ['string', 'max:100', 'distinct:strict'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return PaymentImportUploadData::messages();
    }
}
