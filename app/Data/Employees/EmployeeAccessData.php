<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Enums\PermissionEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\Bail;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
#[MergeValidationRules]
final class EmployeeAccessData extends Data
{
    /** @param list<string>|null $permissions */
    public function __construct(
        #[Bail, Required, Email, Max(255)]
        public readonly string $email,
        #[Bail, Required, Max(100)]
        public readonly string $role_name,
        public readonly ?array $permissions = [],
    ) {}

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return EmployeeValidationText::attributes();
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return EmployeeValidationText::messages();
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
