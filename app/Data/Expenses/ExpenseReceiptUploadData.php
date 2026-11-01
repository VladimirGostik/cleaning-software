<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseReceiptUploadData extends Data
{
    public function __construct(
        #[LiteralTypeScriptType('File')]
        public readonly UploadedFile $receipt,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        /** @var list<string> $mimes */
        $mimes = config('expenses.receipt.allowed_mimes', []);
        $maxKb = config('expenses.receipt.max_size_kb', 10240);

        return [
            'receipt' => ['required', 'file', 'mimetypes:'.implode(',', $mimes), 'max:'.(is_numeric($maxKb) ? (int) $maxKb : 10240)],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'receipt.mimetypes' => __('app.expense_receipt_invalid_type'),
            'receipt.max' => __('app.expense_receipt_too_large'),
        ];
    }
}
