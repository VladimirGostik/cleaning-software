<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use App\Enums\TenantColorEnum;
use App\Models\Tenant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TenantListItemData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $is_active,
        public readonly ?TenantColorEnum $color,
    ) {}

    public static function fromModel(Tenant $tenant): self
    {
        return new self(
            id: $tenant->id,
            name: $tenant->name,
            is_active: $tenant->is_active,
            color: $tenant->interface?->color,
        );
    }
}
