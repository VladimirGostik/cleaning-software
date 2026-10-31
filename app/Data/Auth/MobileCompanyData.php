<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Enums\PermissionEnum;
use App\Enums\TenantColorEnum;
use App\Services\Dashboard\TenantVisibility;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class MobileCompanyData extends Data
{
    /** @param list<PermissionEnum> $permissions */
    public function __construct(
        public readonly string $tenant_id,
        public readonly string $name,
        public readonly ?TenantColorEnum $color,
        public readonly bool $mobile_access,
        public readonly array $permissions,
    ) {}

    public static function fromVisibility(TenantVisibility $visibility): self
    {
        return new self(
            tenant_id: $visibility->tenant->id,
            name: $visibility->tenant->name,
            color: $visibility->tenant->interface?->color,
            mobile_access: $visibility->mobileAccess,
            permissions: $visibility->permissions,
        );
    }
}
