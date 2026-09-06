<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use App\Enums\TenantColorEnum;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AddTenantData extends Data
{
    public function __construct(
        #[Required, Max(255)]
        public readonly string $name,
        #[Required, Max(20)]
        public readonly string $ico,
        public readonly ?TenantColorEnum $color = null,
        public readonly bool $copy_settings = false,
        #[Nullable, Email, Max(255)]
        public readonly ?string $leader_email = null,
    ) {}
}
