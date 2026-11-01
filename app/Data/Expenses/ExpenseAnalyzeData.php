<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseAnalyzeData extends Data
{
    public function __construct(
        #[LiteralTypeScriptType('File | null')]
        public readonly ?UploadedFile $receipt,
        public readonly ?string $text,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        /** @var list<string> $mimes */
        $mimes = config('expenses.receipt.allowed_mimes', []);
        $maxKb = config('expenses.receipt.max_size_kb', 10240);

        return [
            'receipt' => [
                'nullable', 'file', 'required_without:text', 'prohibits:text',
                'mimetypes:'.implode(',', $mimes), 'max:'.(is_numeric($maxKb) ? (int) $maxKb : 10240),
            ],
            'text' => ['nullable', 'string', 'max:5000', 'required_without:receipt'],
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
