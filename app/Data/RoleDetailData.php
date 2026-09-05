<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Role;
use App\Services\RoleService;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class RoleDetailData extends Data
{
    /** @param array<int, string> $permissions */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $permissions,
        public readonly int $users_count,
        public readonly bool $is_system,
    ) {}

    public static function fromModel(Role $role): self
    {
        return new self(
            id: $role->id,
            name: $role->name,
            permissions: $role->permissions->pluck('name')->sort()->values()->toArray(),
            users_count: $role->users()->count(),
            is_system: in_array($role->name, RoleService::SYSTEM_ROLES, true),
        );
    }
}
