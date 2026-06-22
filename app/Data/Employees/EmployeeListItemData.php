<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Models\TenantMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeListItemData extends Data
{
    public function __construct(
        public string $id,
        public string $display_name,
        public string $email,
        public ?string $phone,
        public ?string $role_name,
        public int $assigned_objects_count,
        public ?string $employment_type,
        public bool $is_active,
    ) {}

    public static function fromModel(TenantMembership $membership): self
    {
        return new self(
            id: $membership->id,
            display_name: $membership->display_name,
            email: $membership->user?->email ?? '',
            phone: $membership->phone,
            role_name: $membership->user?->roles->first()?->name,
            assigned_objects_count: 0,
            employment_type: null,
            is_active: (bool) $membership->is_active,
        );
    }
}
