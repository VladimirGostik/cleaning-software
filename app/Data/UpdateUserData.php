<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class UpdateUserData extends Data
{
    /** @param array<int, string> $roles */
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        #[Required, Email, Max(255)]
        public readonly string $email,
        public readonly bool $is_active = true,
        public readonly array $roles = [],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $userId = request()->route('user')?->id;

        return [
            'email' => [Rule::unique('users', 'email')->ignore($userId)],
        ];
    }
}
