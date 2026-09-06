<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class CreateUserData extends Data
{
    /** @param array<int, string> $roles */
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        #[Required, Email, Max(255)]
        public readonly string $email,
        public readonly ?string $password = null,
        public readonly ?string $password_confirmation = null,
        public readonly bool $is_active = true,
        public readonly array $roles = [],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $email = request()->input('email');
        $emailExists = is_string($email) && User::query()->where('email', $email)->exists();

        return [
            'password' => [
                Rule::requiredIf(! $emailExists),
                'nullable', 'string', 'min:8', 'max:255', 'confirmed',
            ],
            'roles.*' => [Rule::exists('roles', 'name')->where('tenant_id', current_tenant_id())],
        ];
    }
}
