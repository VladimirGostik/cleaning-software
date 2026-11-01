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
        $maxKb = config('expenses.csv.max_size_kb', 1024);

        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:'.(is_numeric($maxKb) ? (int) $maxKb : 1024)],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'file.max' => __('app.csv_too_large'),
            'file.extensions' => __('app.csv_invalid'),
        ];
    }
}
