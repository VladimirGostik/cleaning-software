<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Data\Contracts\EmploymentContractUpsertData;
use App\Enums\EmployeeTypeEnum;
use App\Enums\PermissionEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class EmployeeStoreData extends Data
{
    /** @param list<string>|null $permissions */
    public function __construct(
        #[Required]
        public readonly bool $has_app_access,
        #[Required]
        public readonly EmployeeTypeEnum $employee_type,
        #[RequiredIf('has_app_access', 'true'), Nullable, Email, Max(255)]
        public readonly ?string $email,
        #[RequiredIf('has_app_access', 'false'), Nullable, Max(100)]
        public readonly ?string $first_name,
        #[RequiredIf('has_app_access', 'false'), Nullable, Max(100)]
        public readonly ?string $last_name,
        #[Nullable, Max(50)]
        public readonly ?string $phone,
        #[Nullable, Max(100)]
        public readonly ?string $position,
        #[RequiredIf('has_app_access', 'true'), Nullable, Max(100)]
        public readonly ?string $role_name,
        #[RequiredIf('employee_type', 'self_employed'), Nullable, Max(32)]
        public readonly ?string $company_ico,
        #[Nullable, Max(255)]
        public readonly ?string $company_name,
        public readonly ?array $permissions = [],
        #[Nullable]
        public readonly ?EmploymentContractUpsertData $employment = null,
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
    ) {}

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return EmployeeValidationText::attributes();
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [...EmployeeValidationText::messages(), 'national_id.regex' => __('app.employee_national_id_invalid')];
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
