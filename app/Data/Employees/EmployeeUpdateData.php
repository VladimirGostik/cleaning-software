<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Enums\PermissionEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class EmployeeUpdateData extends Data
{
    /** @param list<string>|null $permissions */
    public function __construct(
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
        #[Nullable, Date]
        public readonly ?string $birth_date = null,
        #[Nullable, Regex('/^\d{6}\/?\d{3,4}$/')]
        public readonly ?string $national_id = null,
        #[Nullable, Max(255)]
        public readonly ?string $street = null,
        #[Nullable, Max(255)]
        public readonly ?string $city = null,
        #[Nullable, Max(32)]
        public readonly ?string $postal_code = null,
        #[Nullable, Max(32)]
        public readonly ?string $company_ico = null,
    ) {}

    /** @return array<string, string> */
    public static function messages(): array
    {
        return ['national_id.regex' => __('app.employee_national_id_invalid')];
    }

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
