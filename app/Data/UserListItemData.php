<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class UserListItemData extends Data
{
    /** @param array<int, string> $roles */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly bool $is_active,
        public readonly string $locale,
        public readonly array $roles,
        public readonly string $created_at,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            is_active: $user->is_active,
            locale: $user->locale,
            roles: $user->roles->pluck('name')->sort()->values()->toArray(),
            created_at: $user->created_at?->toIso8601String() ?? '',
        );
    }
}
