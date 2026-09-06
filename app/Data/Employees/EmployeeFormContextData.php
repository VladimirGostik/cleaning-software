<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Data\PermissionGroupData;
use App\Data\RoleListItemData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeFormContextData extends Data
{
    /**
     * @param  RoleListItemData[]  $roles
     * @param  PermissionGroupData[]  $permission_groups
     */
    public function __construct(
        #[DataCollectionOf(RoleListItemData::class)]
        public readonly array $roles,
        #[DataCollectionOf(PermissionGroupData::class)]
        public readonly array $permission_groups,
    ) {}
}
