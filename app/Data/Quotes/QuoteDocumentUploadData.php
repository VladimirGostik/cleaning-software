<?php

declare(strict_types=1);

namespace App\Data\Quotes;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

/**
 * Deliberately no #[TypeScript]: request-only, never rendered to Inertia,
 * and holds an UploadedFile the transformer cannot represent.
 */
final class QuoteDocumentUploadData extends Data
{
    public function __construct(
        public UploadedFile $document,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'document' => [
                'required',
                'file',
                'mimetypes:' . implode(',', config('documents.allowed_mimes')),
                'max:' . config('documents.max_size_kb'),
            ],
        ];
    }
}
