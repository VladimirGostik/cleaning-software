<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use App\Models\Tenant;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class TenantListItemData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $is_active,
    ) {}

    public static function fromModel(Tenant $tenant): self
    {
        return new self(
            id: $tenant->id,
            name: $tenant->name,
            is_active: $tenant->is_active,
        );
    }
}
