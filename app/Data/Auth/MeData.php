<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Enums\PermissionEnum;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MeData extends Data
{
    /** @param list<PermissionEnum> $permissions */
    public function __construct(
        public readonly string $userId,
        public readonly string $activeTenantId,
        public readonly array $permissions,
    ) {}

    /** Used by ResponseFromSpatieData Scribe strategy to generate example docs. */
    public static function fromModel(User $user): self
    {
        return new self(
            userId: $user->id,
            activeTenantId: app()->bound('current_tenant_id') ? current_tenant_id() : '0195d123-0000-7000-0000-000000000002',
            permissions: [],
        );
    }
}
