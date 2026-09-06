<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PermissionEnum;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PermissionItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly PermissionEnum $name,
        public readonly string $label,
    ) {}
}
