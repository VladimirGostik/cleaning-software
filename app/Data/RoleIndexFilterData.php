<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RoleIndexFilterData extends Data
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $per_page = null,
    ) {}
}
