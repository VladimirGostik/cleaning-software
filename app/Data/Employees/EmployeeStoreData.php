<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Data\Contracts\EmploymentContractUpsertData;
use App\Enums\PermissionEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class EmployeeStoreData extends Data
{
    /** @param list<string>|null $permissions */
    public function __construct(
        #[Required, Email, Max(255)]
        public readonly string $email,
        #[Nullable, Max(100)]
        public readonly ?string $first_name,
        #[Nullable, Max(100)]
        public readonly ?string $last_name,
        #[Nullable, Max(50)]
        public readonly ?string $phone,
        #[Nullable, Max(100)]
        public readonly ?string $position,
        #[Required, Max(100)]
        public readonly string $role_name,
        public readonly ?array $permissions = [],
        #[Nullable]
        public readonly ?EmploymentContractUpsertData $employment = null,
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
            'permissions.*' => ['string', Rule::in(PermissionEnum::values())],
        ];
    }
}
