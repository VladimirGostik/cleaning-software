<?php

declare(strict_types=1);

namespace App\Data\Contracts;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ContractFileConstraintsData extends Data
{
    public function __construct(
        public readonly int $max_size_kb,
        public readonly int $max_count,
        /** @var string[] */
        public readonly array $allowed_mimes,
        /** @var string[] */
        public readonly array $allowed_extensions,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array<string, string> $types */
        $types = config('documents.contract_files.types');
        /** @var list<string> $extensions */
        $extensions = config('documents.contract_files.extensions');

        return new self(
            max_size_kb: config()->integer('documents.contract_files.max_size_kb'),
            max_count: config()->integer('documents.contract_files.max_attachments'),
            allowed_mimes: array_keys($types),
            allowed_extensions: $extensions,
        );
    }
}
