<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Role;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RoleListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $permissions_count,
        public readonly int $users_count,
        public readonly bool $is_system,
    ) {}

    public static function fromModel(Role $role): self
    {
        return new self(
            id: (string) $role->id,
            name: $role->name,
            permissions_count: (int) ($role->permissions_count ?? $role->permissions()->count()),
            users_count: (int) ($role->users_count ?? $role->users()->count()),
            is_system: $role->isSystem(),
        );
    }
}
