<?php

declare(strict_types=1);

namespace App\Data\Employees;

use App\Data\Contracts\EmploymentContractUpsertData;
use App\Enums\PermissionEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EmployeeUpsertData extends Data
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        #[Required, Email]
        public string $email,
        #[Nullable, Max(255)]
        public ?string $first_name,
        #[Nullable, Max(255)]
        public ?string $last_name,
        #[Nullable, Max(50)]
        public ?string $phone,
        #[Required, Max(100)]
        public string $role_name,
        /** @var list<string> */
        public array $permissions = [],
        #[Nullable]
        public ?EmploymentContractUpsertData $employment = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'role_name' => [
                'required',
                Rule::exists('roles', 'name')->where('tenant_id', app('current_tenant_id')),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionEnum::values())],
        ];
    }
}
