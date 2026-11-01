<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseImportUploadData extends Data
{
    public function __construct(
        #[LiteralTypeScriptType('File')]
        public readonly UploadedFile $file,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xml', 'max:'.config()->integer('expenses.statement.max_size_kb')],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'file.max' => __('app.expense_import_file_too_large'),
            'file.extensions' => __('app.expense_import_file_invalid'),
        ];
    }
}
