<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class NavigationItemData extends Data
{
    /** @param  array<int, NavigationItemData>  $children */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $href,
        public readonly string $icon,
        public readonly int $order,
        public readonly array $children = [],
    ) {}
}
