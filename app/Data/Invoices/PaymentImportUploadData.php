<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportUploadData extends Data
{
    /** @param  list<UploadedFile>  $files */
    public function __construct(
        #[LiteralTypeScriptType('File[]')]
        public readonly array $files,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:'.config()->integer('invoicing.payment_import.max_files')],
            'files.*' => ['required', 'file', 'extensions:xml', 'max:'.config()->integer('invoicing.payment_import.max_file_kb')],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'files.max' => __('app.payment_import_too_many_files', ['max' => config()->integer('invoicing.payment_import.max_files')]),
            'files.*.max' => __('app.expense_import_file_too_large'),
            'files.*.extensions' => __('app.expense_import_file_invalid'),
        ];
    }
}
