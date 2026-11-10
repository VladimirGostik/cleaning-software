<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PaymentImportData extends Data
{
    /**
     * @param  list<UploadedFile>  $files
     * @param  list<PaymentImportRowInputData>  $rows
     */
    public function __construct(
        #[LiteralTypeScriptType('File[]')]
        public readonly array $files,
        #[DataCollectionOf(PaymentImportRowInputData::class)]
        public readonly array $rows,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            ...PaymentImportUploadData::rules(),
            'rows' => ['required', 'array', 'min:1', 'max:'.config()->integer('invoicing.payment_import.max_rows')],
            'rows.*.reference' => ['distinct:strict'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return PaymentImportUploadData::messages();
    }
}
