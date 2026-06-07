<?php

declare(strict_types=1);

namespace App\Data\Tenants;

use App\Enums\TenantColorEnum;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AddTenantData extends Data
{
    public function __construct(
        #[Required]
        public string $name,
        #[Required]
        public string $ico,
        public ?TenantColorEnum $color = null,
        public bool $copy_settings = false,
        #[Email]
        public ?string $leader_email = null,
    ) {}
}
