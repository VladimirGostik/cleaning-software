<?php

declare(strict_types=1);

namespace App\Data\Employees;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class EmployeeRoleData extends Data
{
    public function __construct(
        #[Required, Max(100)]
        public readonly string $role_name,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        /** @var string|null $tenantId */
        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return [
            'role_name' => [
                Rule::exists('roles', 'name')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
