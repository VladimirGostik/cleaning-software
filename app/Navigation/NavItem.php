<?php

declare(strict_types=1);

namespace App\Navigation;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class NavItem
{
    /**
     * @param  string  $label  Translation key (e.g. `app.users`)
     * @param  string  $route  Named route (e.g. `users.index`)
     * @param  string  $icon  Heroicon name (e.g. `UsersIcon`) — must match the FE icon map
     * @param  string|null  $permission  Spatie permission key OR policy ability. If null, the item is visible to any authenticated user.
     * @param  string|null  $policyModel  FQCN of the model passed to `Gate::allows($permission, $policyModel)`. If null, `$permission` is treated as a Spatie permission string and checked via `$user->can($permission)`.
     * @param  string|null  $group  Parent group key — items with the same `group` are rendered as children under a collapsible parent. `null` = top-level.
     * @param  int  $order  Sort order within group/top-level (asc).
     */
    public function __construct(
        public readonly string $label,
        public readonly string $route,
        public readonly string $icon,
        public readonly ?string $permission = null,
        public readonly ?string $policyModel = null,
        public readonly ?string $group = null,
        public readonly int $order = 100,
    ) {}
}
