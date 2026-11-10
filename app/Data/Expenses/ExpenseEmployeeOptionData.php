<?php

declare(strict_types=1);

namespace App\Data\Expenses;

use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ExpenseEmployeeOptionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenant_id,
        public readonly string $label,
        public readonly bool $is_active,
    ) {}

    /** Expects `user` eager loaded. */
    public static function fromModel(TenantMembership $membership): self
    {
        return new self($membership->id, $membership->tenant_id, $membership->display_name, $membership->is_active);
    }
}
