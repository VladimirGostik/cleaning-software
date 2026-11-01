<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Enums\NavIconEnum;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class NavItem
{
    public function __construct(
        public readonly string $label,
        public readonly string $route,
        public readonly NavIconEnum $icon,
        public readonly ?string $permission = null,
        public readonly ?string $policyModel = null,
        public readonly ?string $group = null,
        public readonly int $order = 100,
    ) {}
}
