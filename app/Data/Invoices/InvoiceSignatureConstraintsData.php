<?php

declare(strict_types=1);

namespace App\Data\Invoices;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class InvoiceSignatureConstraintsData extends Data
{
    public function __construct(
        /** @var string[] */
        public readonly array $allowed_mimes,
        public readonly int $max_size_kb,
    ) {}

    public static function fromConfig(): self
    {
        $maxSizeKb = config('invoicing.signature.max_size_kb', 1024);

        /** @var list<string> $allowedMimes */
        $allowedMimes = array_values(array_filter(
            (array) config('invoicing.signature.allowed_mimes', []),
            static fn (mixed $mime): bool => is_string($mime),
        ));

        return new self(
            allowed_mimes: $allowedMimes,
            max_size_kb: is_numeric($maxSizeKb) ? (int) $maxSizeKb : 1024,
        );
    }
}
