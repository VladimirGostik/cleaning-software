<?php

declare(strict_types=1);

namespace App\Data\DocumentTemplates;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class DocumentTemplateCanData extends Data
{
    public function __construct(
        public readonly bool $update,
        public readonly bool $delete,
        public readonly bool $customize,
        public readonly bool $reset,
        public readonly bool $acknowledge,
    ) {}
}
