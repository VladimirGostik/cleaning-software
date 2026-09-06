<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PermissionGroupData extends Data
{
    /** @param list<PermissionItemData> $permissions */
    public function __construct(
        public readonly string $group,
        public readonly string $group_label,
        public readonly array $permissions,
    ) {}
}
