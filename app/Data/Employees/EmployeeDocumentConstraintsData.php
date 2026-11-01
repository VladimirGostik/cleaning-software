<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Enums\DocumentTypeEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeDocumentConstraintsData extends Data
{
    public function __construct(
        /** @var string[] */
        public readonly array $allowed_mimes,
        public readonly int $max_size_kb,
        /** @var DocumentTypeEnum[] */
        public readonly array $types,
    ) {}

    public static function fromConfig(): self
    {
        $mimes = config('documents.employee_files.allowed_mimes', []);
        $maxSize = config('documents.employee_files.max_size_kb', 10240);

        return new self(
            allowed_mimes: is_array($mimes) ? array_values(array_filter($mimes, is_string(...))) : [],
            max_size_kb: is_numeric($maxSize) ? (int) $maxSize : 10240,
            types: DocumentTypeEnum::employeeFiles(),
        );
    }
}
