<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TokenExpenseReceiptData extends Data
{
    /** @param  list<string>  $expense_ids */
    public function __construct(
        public readonly string $company_id,
        public readonly array $expense_ids,
        #[LiteralTypeScriptType('File')]
        public readonly UploadedFile $file,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        /** @var list<string> $mimes */
        $mimes = config('expenses.receipt.allowed_mimes', []);

        return [
            'company_id' => ['required', 'uuid'],
            'expense_ids' => ['required', 'array', 'min:1', 'max:50'],
            'expense_ids.*' => ['required', 'uuid', 'distinct:strict'],
            'file' => ['required', 'file', 'mimetypes:'.implode(',', $mimes), 'max:'.config()->integer('expenses.receipt.max_size_kb')],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'file.mimetypes' => __('app.expense_receipt_invalid_type'),
            'file.max' => __('app.expense_receipt_too_large'),
        ];
    }
}
