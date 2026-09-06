<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\PermissionEnum;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class UpdateRoleData extends Data
{
    /** @param array<int, string> $permissions */
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        public readonly array $permissions = [],
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'permissions.*' => [Rule::in(PermissionEnum::values())],
        ];
    }
}
